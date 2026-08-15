<?php
/**
 * Uninstall handler.
 *
 * Runs only on real uninstall (never on deactivation). Data is deleted
 * only when the administrator opted in via
 * Settings → Advanced → "Delete plugin data on uninstall".
 *
 * @package PCM
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$pcm_settings = get_option( 'pcm_settings', array() );
$pcm_delete   = is_array( $pcm_settings ) && ! empty( $pcm_settings['advanced']['delete_on_uninstall'] );

// Cron events are always cleared.
wp_clear_scheduled_hook( 'pcm_cleanup_consents' );

if ( ! $pcm_delete ) {
	return;
}

global $wpdb;

/**
 * Removes the plugin's data for one site.
 *
 * @param wpdb $wpdb DB handle.
 */
function pcm_uninstall_site( $wpdb ) {
	$table = $wpdb->prefix . 'pcm_consents';
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	// phpcs:enable

	delete_option( 'pcm_settings' );
	delete_option( 'pcm_db_version' );
	delete_option( 'pcm_last_scan' );
	delete_option( 'pcm_ignored_conflicts' );
	delete_option( 'pcm_conflict_mitigations' );
	delete_transient( 'pcm_consent_stats' );

	// Per-user notice dismissals.
	delete_metadata( 'user', 0, 'pcm_legal_notice_dismissed', '', true );
}

if ( is_multisite() ) {
	$pcm_site_ids = get_sites( array( 'fields' => 'ids' ) );
	foreach ( $pcm_site_ids as $pcm_site_id ) {
		switch_to_blog( $pcm_site_id );
		pcm_uninstall_site( $wpdb );
		restore_current_blog();
	}
} else {
	pcm_uninstall_site( $wpdb );
}
