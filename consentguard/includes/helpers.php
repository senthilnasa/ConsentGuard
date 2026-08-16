<?php
/**
 * Global helper functions. All functions are pcm_ prefixed.
 *
 * @package PCM
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns the full settings array (defaults merged with saved values).
 *
 * @return array
 */
function pcm_get_settings() {
	return PCM\Settings::instance()->all();
}

/**
 * Returns a single setting using dot notation, e.g. 'ga4.measurement_id'.
 *
 * @param string $key     Dot-notation key.
 * @param mixed  $default Fallback value.
 * @return mixed
 */
function pcm_get_setting( $key, $default = null ) {
	return PCM\Settings::instance()->get( $key, $default );
}

/**
 * Whether the current visitor context should render the consent banner.
 *
 * @return bool
 */
function pcm_should_render_banner() {
	if ( is_admin() ) {
		return false;
	}

	// Elementor editor / preview must never show the banner.
	if ( pcm_is_elementor_editor() && pcm_get_setting( 'banner.hide_in_elementor', true ) ) {
		return false;
	}

	if ( ! pcm_get_setting( 'consent.banner_enabled', true ) ) {
		return false;
	}

	/**
	 * Filters whether the consent banner should render for the current request.
	 *
	 * @param bool $should Render the banner.
	 */
	return (bool) apply_filters( 'pcm_should_render_banner', true );
}

/**
 * Whether the request is inside the Elementor editor or an Elementor preview.
 *
 * @return bool
 */
function pcm_is_elementor_editor() {
	if ( did_action( 'elementor/loaded' ) && class_exists( '\Elementor\Plugin' ) ) {
		$elementor = \Elementor\Plugin::$instance;
		if ( isset( $elementor->editor ) && $elementor->editor->is_edit_mode() ) {
			return true;
		}
		if ( isset( $elementor->preview ) && $elementor->preview->is_preview_mode() ) {
			return true;
		}
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only context detection.
	return isset( $_GET['elementor-preview'] );
}

/**
 * Sanitizes a consent category slug.
 *
 * @param string $slug Raw slug.
 * @return string
 */
function pcm_sanitize_category_slug( $slug ) {
	$slug = sanitize_key( $slug );
	return substr( $slug, 0, 40 );
}

/**
 * Sanitizes script markup supplied by an administrator.
 *
 * Only users with the `unfiltered_html` capability may store <script> markup.
 * For everyone else the markup is stripped down to safe HTML, which
 * effectively prevents lower-capability users from injecting scripts.
 *
 * @param string $code Raw script markup.
 * @return string
 */
function pcm_sanitize_script_code( $code ) {
	if ( current_user_can( 'unfiltered_html' ) ) {
		return trim( (string) $code );
	}
	return trim( wp_kses_post( (string) $code ) );
}

/**
 * Validates a GA4 measurement ID (G-XXXXXXXXXX).
 *
 * @param string $id Raw ID.
 * @return string Empty string when invalid.
 */
function pcm_sanitize_ga4_id( $id ) {
	$id = strtoupper( trim( (string) $id ) );
	return preg_match( '/^G-[A-Z0-9]{4,16}$/', $id ) ? $id : '';
}

/**
 * Validates a GTM container ID (GTM-XXXXXXX).
 *
 * @param string $id Raw ID.
 * @return string Empty string when invalid.
 */
function pcm_sanitize_gtm_id( $id ) {
	$id = strtoupper( trim( (string) $id ) );
	return preg_match( '/^GTM-[A-Z0-9]{4,12}$/', $id ) ? $id : '';
}

/**
 * Validates a Microsoft Clarity project ID.
 *
 * @param string $id Raw ID.
 * @return string Empty string when invalid.
 */
function pcm_sanitize_clarity_id( $id ) {
	$id = trim( (string) $id );
	return preg_match( '/^[a-z0-9]{5,20}$/i', $id ) ? $id : '';
}

/**
 * Validates a Cloudflare Web Analytics token.
 *
 * @param string $token Raw token.
 * @return string Empty string when invalid.
 */
function pcm_sanitize_cf_token( $token ) {
	$token = trim( (string) $token );
	return preg_match( '/^[a-f0-9]{16,64}$/i', $token ) ? $token : '';
}

/**
 * Sanitizes a hostname (used for the blocker domain lists).
 *
 * @param string $domain Raw domain.
 * @return string Empty string when invalid.
 */
function pcm_sanitize_domain( $domain ) {
	$domain = strtolower( trim( (string) $domain ) );
	$domain = preg_replace( '#^https?://#', '', $domain );
	$domain = rtrim( explode( '/', $domain )[0], '.' );
	if ( '' === $domain || ! preg_match( '/^(\*\.)?([a-z0-9-]+\.)+[a-z]{2,}$/', $domain ) ) {
		return '';
	}
	return $domain;
}

/**
 * Generates a random identifier suitable for consent records.
 *
 * @return string
 */
function pcm_generate_uuid() {
	return wp_generate_uuid4();
}

/**
 * Returns the list of built-in category slugs.
 *
 * @return string[]
 */
function pcm_builtin_categories() {
	return array( 'necessary', 'functional', 'analytics', 'marketing', 'preferences' );
}

/**
 * Derives banner colors from the active theme's global palette so the
 * consent UI matches the site design ("use theme colors" banner setting).
 *
 * Block themes (theme.json) expose a palette; common slugs are probed in
 * priority order. Classic themes without a palette return an empty array
 * and the configured colors apply instead.
 *
 * @return array{primary?: string, text?: string, background?: string}
 */
function pcm_theme_colors() {
	if ( ! function_exists( 'wp_get_global_settings' ) ) {
		return array();
	}

	$palette = wp_get_global_settings( array( 'color', 'palette' ) );
	$flat    = array();
	foreach ( array( 'custom', 'theme', 'default' ) as $origin ) {
		if ( ! empty( $palette[ $origin ] ) && is_array( $palette[ $origin ] ) ) {
			foreach ( $palette[ $origin ] as $entry ) {
				if ( ! empty( $entry['slug'] ) && ! empty( $entry['color'] ) && ! isset( $flat[ $entry['slug'] ] ) ) {
					$flat[ $entry['slug'] ] = sanitize_hex_color( $entry['color'] );
				}
			}
		}
	}
	if ( empty( $flat ) ) {
		return array();
	}

	$pick = static function ( array $slugs ) use ( $flat ) {
		foreach ( $slugs as $slug ) {
			if ( ! empty( $flat[ $slug ] ) ) {
				return $flat[ $slug ];
			}
		}
		return '';
	};

	$colors = array_filter(
		array(
			'primary'    => $pick( array( 'primary', 'accent', 'accent-1', 'vivid-cyan-blue', 'contrast' ) ),
			'text'       => $pick( array( 'contrast', 'foreground', 'black' ) ),
			'background' => $pick( array( 'base', 'background', 'white' ) ),
		)
	);

	/**
	 * Filters the theme-derived consent UI colors.
	 *
	 * @param array $colors {primary, text, background} hex values.
	 */
	return apply_filters( 'pcm_theme_colors', $colors );
}
