<?php
/**
 * Privacy-preserving jurisdiction resolution.
 *
 * @package PCM
 */

namespace PCM;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves the visitor's jurisdiction profile without external lookups.
 *
 * Only trusted infrastructure headers (Cloudflare, common GeoIP server
 * modules) are consulted. No IP address is ever stored and no third-party
 * geolocation API is called. When no signal is available, the configured
 * default profile applies — which must therefore be the strictest one the
 * site needs.
 */
class Geolocation_Manager {

	/**
	 * Registers hooks (none needed; consumed by Frontend).
	 */
	public function register() {}

	/**
	 * Country headers to consult, in priority order.
	 *
	 * @return string[]
	 */
	private function country_headers() {
		/**
		 * Filters the server headers used for country detection.
		 *
		 * @param string[] $headers $_SERVER keys, checked in order.
		 */
		return apply_filters(
			'pcm_geo_headers',
			array(
				'HTTP_CF_IPCOUNTRY',      // Cloudflare.
				'GEOIP_COUNTRY_CODE',     // mod_geoip / nginx geoip.
				'HTTP_X_COUNTRY_CODE',    // Some CDNs / reverse proxies.
				'HTTP_CLOUDFRONT_VIEWER_COUNTRY', // AWS CloudFront.
			)
		);
	}

	/**
	 * Detects the visitor country from trusted headers.
	 *
	 * @return string ISO 3166-1 alpha-2 code or '' when unknown.
	 */
	public function detect_country() {
		if ( ! pcm_get_setting( 'jurisdictions.geo_enabled', false ) ) {
			return '';
		}

		foreach ( $this->country_headers() as $header ) {
			if ( empty( $_SERVER[ $header ] ) ) {
				continue;
			}
			$code = strtoupper( sanitize_key( wp_unslash( $_SERVER[ $header ] ) ) );
			if ( preg_match( '/^[A-Z]{2}$/', $code ) && 'XX' !== $code && 'T1' !== $code ) {
				return $code;
			}
		}
		return '';
	}

	/**
	 * Resolves the consent profile for a country code.
	 *
	 * @param string $country ISO country code ('' = unknown).
	 * @return array{key: string, profile: array}
	 */
	public function resolve_profile( $country = null ) {
		if ( null === $country ) {
			$country = $this->detect_country();
		}

		$rules    = (array) pcm_get_setting( 'jurisdictions.rules', array() );
		$profiles = (array) pcm_get_setting( 'jurisdictions.profiles', Settings::default_profiles() );
		$default  = (string) pcm_get_setting( 'jurisdictions.default_profile', 'default' );

		$eu = array( 'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE', 'IS', 'LI', 'NO' );

		$key = $default;
		if ( '' !== $country ) {
			if ( isset( $rules[ $country ] ) ) {
				$key = $rules[ $country ];
			} elseif ( in_array( $country, $eu, true ) && isset( $profiles['gdpr'] ) ) {
				$key = 'gdpr';
			}
		}

		if ( ! isset( $profiles[ $key ] ) ) {
			$key = isset( $profiles['default'] ) ? 'default' : (string) array_key_first( $profiles );
		}

		/**
		 * Filters the resolved jurisdiction profile.
		 *
		 * @param array  $resolved {key, profile}.
		 * @param string $country  Detected country ('' when unknown).
		 */
		return apply_filters(
			'pcm_resolved_profile',
			array(
				'key'     => $key,
				'profile' => $profiles[ $key ],
			),
			$country
		);
	}
}
