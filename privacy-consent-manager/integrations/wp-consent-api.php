<?php
/**
 * WP Consent API bridge.
 *
 * When the wp-consent-api plugin is active, this plugin registers itself as
 * a consent-API-compliant CMP and (client-side, see analytics.js) mirrors
 * every consent decision via wp_set_consent(), so other consent-aware
 * plugins observe the same state:
 *
 *   functional  -> functional
 *   analytics   -> statistics
 *   marketing   -> marketing
 *   preferences -> preferences
 *
 * @package PCM
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'wp_consent_api_registered_' . PCM_PLUGIN_BASENAME, '__return_true' );

add_action(
	'plugins_loaded',
	static function () {
		if ( ! function_exists( 'wp_has_consent' ) ) {
			return;
		}
		// Declare opt-in as this site's consent model to the Consent API.
		if ( ! defined( 'WP_CONSENT_API_ACTIVE_POLICY' ) ) {
			add_filter(
				'wp_get_consent_type',
				static function () {
					return 'optin';
				}
			);
		}
	},
	20
);
