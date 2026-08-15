<?php
/**
 * Detects installed tracking-related plugins.
 *
 * @package PCM
 */

namespace PCM;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only detection of other plugins that may inject analytics.
 * Never modifies other plugins; only inspects activation state and,
 * where a supported public API exists, configuration.
 */
class Plugin_Detector {

	/**
	 * Registers hooks (none; consumed by conflict manager).
	 */
	public function register() {}

	/**
	 * Known tracking plugins and what they may inject.
	 *
	 * @return array<string, array>
	 */
	public function known_plugins() {
		$known = array(
			'google-site-kit/google-site-kit.php'                => array(
				'name'     => 'Site Kit by Google',
				'services' => array( 'ga4', 'gtm' ),
				'slug'     => 'site-kit',
			),
			'microsoft-clarity/clarity.php'                      => array(
				'name'     => 'Microsoft Clarity',
				'services' => array( 'clarity' ),
				'slug'     => 'microsoft-clarity',
			),
			'cloudflare/cloudflare.php'                          => array(
				'name'     => 'Cloudflare',
				'services' => array( 'cloudflare' ),
				'slug'     => 'cloudflare',
			),
			'google-analytics-for-wordpress/googleanalytics.php' => array(
				'name'     => 'MonsterInsights',
				'services' => array( 'ga4' ),
				'slug'     => 'monsterinsights',
			),
			'ga-google-analytics/ga-google-analytics.php'        => array(
				'name'     => 'GA Google Analytics',
				'services' => array( 'ga4' ),
				'slug'     => 'ga-google-analytics',
			),
			'googleanalytics/googleanalytics.php'                => array(
				'name'     => 'Google Analytics for WordPress by ShareThis',
				'services' => array( 'ga4' ),
				'slug'     => 'sharethis-ga',
			),
			'duracelltomi-google-tag-manager/duracelltomi-google-tag-manager-for-wordpress.php' => array(
				'name'     => 'GTM4WP',
				'services' => array( 'gtm' ),
				'slug'     => 'gtm4wp',
			),
			'metronet-tag-manager/metronet-tag-manager.php'      => array(
				'name'     => 'Metronet Tag Manager',
				'services' => array( 'gtm' ),
				'slug'     => 'metronet-gtm',
			),
			'official-facebook-pixel/facebook-for-wordpress.php' => array(
				'name'     => 'Meta Pixel for WordPress',
				'services' => array( 'meta_pixel' ),
				'slug'     => 'meta-pixel',
			),
			'pixelyoursite/facebook-pixel-master.php'            => array(
				'name'     => 'PixelYourSite',
				'services' => array( 'meta_pixel', 'ga4' ),
				'slug'     => 'pixelyoursite',
			),
			'wp-analytify/wp-analytify.php'                      => array(
				'name'     => 'Analytify',
				'services' => array( 'ga4' ),
				'slug'     => 'analytify',
			),
			'host-analyticsjs-local/host-analyticsjs-local.php'  => array(
				'name'     => 'CAOS (Host Analytics Locally)',
				'services' => array( 'ga4' ),
				'slug'     => 'caos',
			),
		);

		/**
		 * Filters the registry of known tracking plugins.
		 *
		 * @param array $known plugin-basename => {name, services, slug}.
		 */
		return apply_filters( 'pcm_known_tracking_plugins', $known );
	}

	/**
	 * Returns active known tracking plugins.
	 *
	 * @return array<string, array> basename => meta (with 'active' => true).
	 */
	public function detect_active() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$found = array();
		foreach ( $this->known_plugins() as $basename => $meta ) {
			if ( is_plugin_active( $basename ) ) {
				$meta['active']     = true;
				$meta['basename']   = $basename;
				$found[ $basename ] = $meta;
			}
		}
		return $found;
	}

	/**
	 * Whether Site Kit is active AND has its Analytics module enabled.
	 * Reads Site Kit's documented module option (read-only).
	 *
	 * @return bool
	 */
	public function site_kit_analytics_active() {
		if ( ! defined( 'GOOGLESITEKIT_VERSION' ) && ! is_plugin_active_safe( 'google-site-kit/google-site-kit.php' ) ) {
			return false;
		}
		$modules = get_option( 'googlesitekit_active_modules', array() );
		if ( ! is_array( $modules ) ) {
			return false;
		}
		return in_array( 'analytics-4', $modules, true ) || in_array( 'analytics', $modules, true );
	}
}

if ( ! function_exists( 'PCM\is_plugin_active_safe' ) ) {
	/**
	 * is_plugin_active() that works on the frontend too.
	 *
	 * @param string $basename Plugin basename.
	 * @return bool
	 */
	function is_plugin_active_safe( $basename ) {
		$active = (array) get_option( 'active_plugins', array() );
		if ( in_array( $basename, $active, true ) ) {
			return true;
		}
		if ( is_multisite() ) {
			$network = (array) get_site_option( 'active_sitewide_plugins', array() );
			return isset( $network[ $basename ] );
		}
		return false;
	}
}
