<?php
/**
 * Site Kit by Google integration shim.
 *
 * Site Kit provides more than analytics (Search Console, PageSpeed,
 * AdSense). We therefore never deactivate it and never touch its database
 * settings. When the administrator enables the "site-kit:ga4" or
 * "site-kit:gtm" mitigation, we use Site Kit's own publicly documented
 * `googlesitekit_{module}_tag_blocked` filters to stop only the duplicate
 * tag output. Everything else in Site Kit keeps working.
 *
 * @package PCM
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'plugins_loaded',
	static function () {
		if ( ! defined( 'GOOGLESITEKIT_VERSION' ) ) {
			return;
		}

		if ( PCM\Plugin_Conflict_Manager::is_mitigation_enabled( 'site-kit:ga4' ) ) {
			// Supported Site Kit filters to suppress the Analytics tag only.
			add_filter( 'googlesitekit_analytics-4_tag_blocked', '__return_true' );
			add_filter( 'googlesitekit_analytics_tag_blocked', '__return_true' );
		}

		if ( PCM\Plugin_Conflict_Manager::is_mitigation_enabled( 'site-kit:gtm' ) ) {
			add_filter( 'googlesitekit_tagmanager_tag_blocked', '__return_true' );
		}
	},
	20
);
