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
			$wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT %d OFFSET %d', $table, $per_page, $offset ),
			ARRAY_A
		);
		$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
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

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT COUNT(*) AS total,
					SUM(CASE WHEN functional = 1 AND analytics = 1 AND marketing = 1 AND preferences = 1 THEN 1 ELSE 0 END) AS accepted_all,
					SUM(CASE WHEN functional = 0 AND analytics = 0 AND marketing = 0 AND preferences = 0 THEN 1 ELSE 0 END) AS rejected_all,
					SUM(analytics) AS analytics_granted,
					SUM(marketing) AS marketing_granted
				FROM %i',
				$table
			),
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
	 * Daily accept/reject/custom counts for the dashboard trend chart.
	 * Cached for an hour.
	 *
	 * @param int $days Days back (max 90).
	 * @return array[] Chronological: {date, accept, reject, custom}.
	 */
	public function get_daily_stats( $days = 30 ) {
		$days   = max( 1, min( 90, (int) $days ) );
		$cached = get_transient( 'pcm_daily_stats_' . $days );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		$table  = $this->table();
		$cutoff = gmdate( 'Y-m-d 00:00:00', time() - $days * DAY_IN_SECONDS );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(created_at) AS day,
					SUM(CASE WHEN action = 'accept_all' THEN 1 ELSE 0 END) AS accept_count,
					SUM(CASE WHEN action IN ('reject_all','withdraw') THEN 1 ELSE 0 END) AS reject_count,
					SUM(CASE WHEN action NOT IN ('accept_all','reject_all','withdraw') THEN 1 ELSE 0 END) AS custom_count
				FROM %i WHERE created_at >= %s GROUP BY DATE(created_at) ORDER BY day ASC",
				$table,
				$cutoff
			),
			ARRAY_A
		);
		// phpcs:enable

		$by_day = array();
		foreach ( (array) $rows as $row ) {
			$by_day[ $row['day'] ] = $row;
		}

		// Fill gaps so the chart has a point for every day.
		$stats = array();
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$day     = gmdate( 'Y-m-d', time() - $i * DAY_IN_SECONDS );
			$stats[] = array(
				'date'   => $day,
				'accept' => (int) ( $by_day[ $day ]['accept_count'] ?? 0 ),
				'reject' => (int) ( $by_day[ $day ]['reject_count'] ?? 0 ),
				'custom' => (int) ( $by_day[ $day ]['custom_count'] ?? 0 ),
			);
		}

		set_transient( 'pcm_daily_stats_' . $days, $stats, HOUR_IN_SECONDS );
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
				$wpdb->prepare( 'DELETE FROM %i WHERE created_at < %s LIMIT 1000', $table, $cutoff )
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
	 * Returns the most recent record matching a UUID as either consent ID
	 * or anonymous ID (used by the proof-of-consent export).
	 *
	 * @param string $uuid UUID.
	 * @return array|null
	 */
	public function find_by_uuid( $uuid ) {
		global $wpdb;
		$table = $this->table();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE consent_id = %s OR anonymous_id = %s ORDER BY id DESC LIMIT 1',
				$table,
				$uuid,
				$uuid
			),
			ARRAY_A
		);
		// phpcs:enable
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Deletes all records matching a UUID as either consent ID or anonymous
	 * ID (admin-initiated erasure from the Consent Records screen).
	 *
	 * @param string $uuid UUID.
	 * @return int Deleted rows.
	 */
	public function delete_by_uuid( $uuid ) {
		global $wpdb;
		$table = $this->table();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$deleted = (int) $wpdb->query(
			$wpdb->prepare( 'DELETE FROM %i WHERE consent_id = %s OR anonymous_id = %s', $table, $uuid, $uuid )
		);
		// phpcs:enable
		if ( $deleted > 0 ) {
			delete_transient( 'pcm_consent_stats' );
		}
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
			$wpdb->prepare( 'DELETE FROM %i WHERE anonymous_id = %s', $table, $anonymous_id )
		);
		// phpcs:enable
	}
}
