<?php
/**
 * WP-CLI commands.
 *
 * @package PCM
 */

namespace PCM;

defined( 'ABSPATH' ) || exit;

/**
 * `wp consentguard <command>` — automation-friendly access to consent
 * statistics, the duplicate-tracking scan, retention cleanup and record
 * export.
 */
class CLI {

	/**
	 * Shows consent statistics.
	 *
	 * ## EXAMPLES
	 *
	 *     wp consentguard stats
	 *
	 * @when after_wp_load
	 */
	public function stats() {
		$stats = ( new Consent_Storage() )->get_stats();
		\WP_CLI\Utils\format_items(
			'table',
			array(
				array(
					'total'        => $stats['total'],
					'accepted_all' => $stats['accepted_all'],
					'rejected_all' => $stats['rejected_all'],
					'customized'   => $stats['customized'],
				),
			),
			array( 'total', 'accepted_all', 'rejected_all', 'customized' )
		);
	}

	/**
	 * Runs the duplicate-tracking homepage scan.
	 *
	 * ## EXAMPLES
	 *
	 *     wp consentguard scan
	 *
	 * @when after_wp_load
	 */
	public function scan() {
		$scanner = pcm()->module( 'duplicates' );
		$result  = $scanner->scan();
		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() );
		}
		foreach ( $result['results'] as $service => $data ) {
			\WP_CLI::log( sprintf(
				'%s: %d instance(s), sources: %s%s',
				$data['label'],
				$data['instances'],
				$data['sources'] ? implode( ', ', $data['sources'] ) : '-',
				$data['duplicate'] ? '  [DUPLICATE]' : ''
			) );
		}
		\WP_CLI::success( 'Scan complete.' );
	}

	/**
	 * Deletes consent records past the configured retention period.
	 *
	 * ## EXAMPLES
	 *
	 *     wp consentguard cleanup
	 *
	 * @when after_wp_load
	 */
	public function cleanup() {
		$deleted = ( new Consent_Storage() )->cleanup_expired();
		\WP_CLI::success( sprintf( '%d expired consent record(s) deleted.', $deleted ) );
	}

	/**
	 * Exports all consent records as CSV.
	 *
	 * ## OPTIONS
	 *
	 * [--file=<path>]
	 * : Write to a file instead of STDOUT.
	 *
	 * ## EXAMPLES
	 *
	 *     wp consentguard export --file=consents.csv
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Named args.
	 */
	public function export( $args, $assoc_args ) {
		$storage = new Consent_Storage();
		$columns = array( 'created_at', 'consent_id', 'anonymous_id', 'action', 'necessary', 'functional', 'analytics', 'marketing', 'preferences', 'extra_categories', 'consent_version', 'policy_version', 'region', 'language' );

		$handle = isset( $assoc_args['file'] )
			? fopen( $assoc_args['file'], 'w' ) // phpcs:ignore WordPress.WP.AlternativeFunctions
			: fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! $handle ) {
			\WP_CLI::error( 'Could not open output for writing.' );
		}

		fputcsv( $handle, $columns );
		$page = 1;
		$rows = 0;
		do {
			$batch = $storage->get_records( $page, 100 );
			foreach ( $batch['items'] as $row ) {
				$line = array();
				foreach ( $columns as $column ) {
					$line[] = isset( $row[ $column ] ) ? $row[ $column ] : '';
				}
				fputcsv( $handle, $line );
				$rows++;
			}
			$page++;
		} while ( count( $batch['items'] ) === 100 );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		if ( isset( $assoc_args['file'] ) ) {
			\WP_CLI::success( sprintf( '%d record(s) exported to %s.', $rows, $assoc_args['file'] ) );
		}
	}
}
