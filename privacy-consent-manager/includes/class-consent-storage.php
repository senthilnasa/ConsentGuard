<?php
/**
 * Server-side consent record storage.
 *
 * @package PCM
 */

namespace PCM;

defined( 'ABSPATH' ) || exit;

/**
 * Persists consent records to {$wpdb->prefix}pcm_consents.
 *
 * Privacy by design: no IP addresses, no user agents, no account linkage.
 * Only an anonymous client-generated identifier is stored so a visitor's
 * consent history can be demonstrated without identifying the visitor.
 */
class Consent_Storage {

	/**
	 * Returns the fully qualified table name.
	 *
	 * @return string
	 */
	public function table() {
		global $wpdb;
		return $wpdb->prefix . 'pcm_consents';
	}

	/**
	 * Inserts a consent record.
	 *
	 * @param array $record Sanitized record from Consent_Manager::sanitize_record().
	 * @return int|false Insert ID or false.
	 */
	public function insert( array $record ) {
		global $wpdb;

		if ( ! pcm_get_setting( 'consent.store_records', true ) ) {
			return false;
		}

		$defaults = array(
			'consent_id'       => pcm_generate_uuid(),
			'anonymous_id'     => '',
			'consent_version'  => '',
			'policy_version'   => '',
			'necessary'        => 1,
			'functional'       => 0,
			'analytics'        => 0,
			'marketing'        => 0,
			'preferences'      => 0,
			'extra_categories' => null,
			'region'           => '',
			'language'         => '',
			'action'           => 'update',
			'created_at'       => current_time( 'mysql', true ),
		);
		$record   = array_intersect_key( array_merge( $defaults, $record ), $defaults );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
		$ok = $wpdb->insert(
			$this->table(),
			$record,
			array( '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		return $ok ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Paginated record listing for the admin UI.
	 *
	 * @param int $page     1-based page.
	 * @param int $per_page Rows per page (max 100).
	 * @return array{items: array, total: int}
	 */
	public function get_records( $page = 1, $per_page = 20 ) {
		global $wpdb;

		$per_page = max( 1, min( 100, (int) $per_page ) );
		$offset   = ( max( 1, (int) $page ) - 1 ) * $per_page;
		$table    = $this->table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table name is trusted.
		$items = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset ),
			ARRAY_A
		);
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		// phpcs:enable

		return array(
			'items' => is_array( $items ) ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Aggregate stats for the dashboard. Cached for an hour to avoid
	 * repeated COUNT queries on large tables.
	 *
	 * @return array
	 */
	public function get_stats() {
		$cached = get_transient( 'pcm_consent_stats' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		$table = $this->table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			"SELECT COUNT(*) AS total,
				SUM(CASE WHEN functional = 1 AND analytics = 1 AND marketing = 1 AND preferences = 1 THEN 1 ELSE 0 END) AS accepted_all,
				SUM(CASE WHEN functional = 0 AND analytics = 0 AND marketing = 0 AND preferences = 0 THEN 1 ELSE 0 END) AS rejected_all,
				SUM(analytics) AS analytics_granted,
				SUM(marketing) AS marketing_granted
			FROM {$table}",
			ARRAY_A
		);
		// phpcs:enable

		$total = isset( $row['total'] ) ? (int) $row['total'] : 0;
		$stats = array(
			'total'             => $total,
			'accepted_all'      => (int) ( $row['accepted_all'] ?? 0 ),
			'rejected_all'      => (int) ( $row['rejected_all'] ?? 0 ),
			'customized'        => max( 0, $total - (int) ( $row['accepted_all'] ?? 0 ) - (int) ( $row['rejected_all'] ?? 0 ) ),
			'analytics_granted' => (int) ( $row['analytics_granted'] ?? 0 ),
			'marketing_granted' => (int) ( $row['marketing_granted'] ?? 0 ),
		);

		set_transient( 'pcm_consent_stats', $stats, HOUR_IN_SECONDS );
		return $stats;
	}

	/**
	 * Deletes records older than the configured retention period.
	 * Runs on the daily pcm_cleanup_consents cron event.
	 *
	 * @return int Deleted row count.
	 */
	public function cleanup_expired() {
		global $wpdb;

		$days = (int) pcm_get_setting( 'consent.retention_days', 365 );
		if ( $days < 1 ) {
			return 0;
		}

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$table  = $this->table();

		// Chunked deletes keep large tables responsive.
		$deleted = 0;
		do {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$batch = (int) $wpdb->query(
				$wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s LIMIT 1000", $cutoff )
			);
			// phpcs:enable
			$deleted += $batch;
		} while ( $batch >= 1000 );

		if ( $deleted > 0 ) {
			delete_transient( 'pcm_consent_stats' );
		}

		/**
		 * Fires after expired consent records were purged.
		 *
		 * @param int $deleted Number of rows removed.
		 */
		do_action( 'pcm_consents_cleaned', $deleted );

		return $deleted;
	}

	/**
	 * Deletes all records for an anonymous identifier (withdrawal/erasure support).
	 *
	 * @param string $anonymous_id Anonymous identifier.
	 * @return int Deleted rows.
	 */
	public function delete_by_anonymous_id( $anonymous_id ) {
		global $wpdb;
		$table = $this->table();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->query(
			$wpdb->prepare( "DELETE FROM {$table} WHERE anonymous_id = %s", $anonymous_id )
		);
		// phpcs:enable
	}
}
