<?php
/**
 * Activation routine.
 *
 * @package PCM
 */

namespace PCM;

defined( 'ABSPATH' ) || exit;

/**
 * Creates the consent table, seeds defaults and schedules cron.
 */
class Activator {

	/**
	 * Runs on plugin activation.
	 *
	 * @param bool $network_wide Network activation on multisite.
	 */
	public static function activate( $network_wide = false ) {
		if ( $network_wide && is_multisite() ) {
			foreach ( get_sites( array( 'fields' => 'ids' ) ) as $site_id ) {
				switch_to_blog( $site_id );
				self::activate_single();
				restore_current_blog();
			}
			return;
		}
		self::activate_single();
	}

	/**
	 * Activation steps for one site.
	 */
	private static function activate_single() {
		self::create_tables();
		self::seed_defaults();

		if ( ! wp_next_scheduled( 'pcm_cleanup_consents' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'pcm_cleanup_consents' );
		}

		update_option( 'pcm_db_version', PCM_DB_VERSION );
	}

	/**
	 * Provisions a newly created multisite subsite (activation hooks do not
	 * run for new sites when the plugin is network-activated).
	 *
	 * @param \WP_Site $new_site New site object.
	 */
	public static function initialize_new_site( $new_site ) {
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! is_plugin_active_for_network( PCM_PLUGIN_BASENAME ) ) {
			return;
		}
		switch_to_blog( (int) $new_site->blog_id );
		self::activate_single();
		restore_current_blog();
	}

	/**
	 * Applies schema/config upgrades after a plugin update (activation hooks
	 * do not run on updates). Hooked to admin_init.
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'pcm_db_version' ) !== PCM_DB_VERSION ) {
			self::create_tables();
			update_option( 'pcm_db_version', PCM_DB_VERSION );
		}
		if ( ! wp_next_scheduled( 'pcm_cleanup_consents' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'pcm_cleanup_consents' );
		}
	}

	/**
	 * Creates (or upgrades) the consent records table.
	 */
	public static function create_tables() {
		global $wpdb;

		$table           = $wpdb->prefix . 'pcm_consents';
		$charset_collate = $wpdb->get_charset_collate();

		// No IP addresses, no user agents, no direct identifiers by design.
		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			consent_id VARCHAR(36) NOT NULL,
			anonymous_id VARCHAR(36) NOT NULL DEFAULT '',
			consent_version VARCHAR(20) NOT NULL DEFAULT '',
			policy_version VARCHAR(20) NOT NULL DEFAULT '',
			necessary TINYINT(1) NOT NULL DEFAULT 1,
			functional TINYINT(1) NOT NULL DEFAULT 0,
			analytics TINYINT(1) NOT NULL DEFAULT 0,
			marketing TINYINT(1) NOT NULL DEFAULT 0,
			preferences TINYINT(1) NOT NULL DEFAULT 0,
			extra_categories TEXT NULL,
			region VARCHAR(8) NOT NULL DEFAULT '',
			language VARCHAR(12) NOT NULL DEFAULT '',
			action VARCHAR(20) NOT NULL DEFAULT 'update',
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY consent_id (consent_id),
			KEY anonymous_id (anonymous_id),
			KEY created_at (created_at),
			KEY consent_version (consent_version)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Stores default settings on first activation only.
	 */
	private static function seed_defaults() {
		if ( false === get_option( 'pcm_settings', false ) ) {
			add_option( 'pcm_settings', Settings::defaults(), '', false );
		}
	}
}
