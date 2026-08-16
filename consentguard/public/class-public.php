<?php
/**
 * Frontend bootstrap: assets, config, banner mount point.
 *
 * @package PCM
 */

namespace PCM;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues the consent UI and exposes configuration to JavaScript.
 *
 * The banner itself is rendered client-side from configuration so pages
 * remain fully cacheable: every visitor receives identical HTML and the
 * browser decides what to show based on the first-party consent cookie.
 */
class Frontend {

	/**
	 * Consent manager.
	 *
	 * @var Consent_Manager
	 */
	private $consent;

	/**
	 * Geolocation.
	 *
	 * @var Geolocation_Manager
	 */
	private $geo;

	/**
	 * Constructor.
	 *
	 * @param Consent_Manager     $consent Consent manager.
	 * @param Geolocation_Manager $geo     Geolocation manager.
	 */
	public function __construct( Consent_Manager $consent, Geolocation_Manager $geo ) {
		$this->consent = $consent;
		$this->geo     = $geo;
	}

	/**
	 * Registers hooks.
	 */
	public function register() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_shortcode( 'pcm_privacy_settings', array( $this, 'shortcode_privacy_settings' ) );
	}

	/**
	 * [pcm_privacy_settings label="..."] renders a button that opens the
	 * consent preferences modal — for footers, policy pages, menus etc.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode_privacy_settings( $atts ) {
		$atts = shortcode_atts(
			array(
				'label' => pcm_get_setting( 'banner.reopen_label', __( 'Privacy Settings', 'consentguard' ) ),
			),
			$atts,
			'pcm_privacy_settings'
		);
		return sprintf(
			'<button type="button" class="pcm-open-preferences pcm-inline-open">%s</button>',
			esc_html( $atts['label'] )
		);
	}

	/**
	 * Asset cache-buster: file mtime under WP_DEBUG so development changes
	 * bypass browser/page caches; the plugin version in production.
	 *
	 * @param string $relative Plugin-relative asset path.
	 * @return string
	 */
	private function asset_version( $relative ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$mtime = @filemtime( PCM_PLUGIN_DIR . $relative ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( $mtime ) {
				return (string) $mtime;
			}
		}
		return PCM_VERSION;
	}

	/**
	 * Enqueues styles and scripts.
	 */
	public function enqueue_assets() {
		if ( ! pcm_should_render_banner() && ! $this->has_managed_scripts() ) {
			return;
		}

		wp_enqueue_style(
			'pcm-banner',
			PCM_PLUGIN_URL . 'public/css/consent-banner.css',
			array(),
			$this->asset_version( 'public/css/consent-banner.css' )
		);
		wp_add_inline_style( 'pcm-banner', $this->banner_css_vars() );

		$debug = (bool) pcm_get_setting( 'advanced.debug', false );

		// debug.js is NOT a dependency: modules guard on window.PCMDebug so
		// production pages never pay for the logger.
		wp_register_script( 'pcm-debug', PCM_PLUGIN_URL . 'public/js/debug.js', array(), $this->asset_version( 'public/js/debug.js' ), false );
		wp_register_script( 'pcm-blocker', PCM_PLUGIN_URL . 'public/js/script-blocker.js', $debug_deps = $debug ? array( 'pcm-debug' ) : array(), $this->asset_version( 'public/js/script-blocker.js' ), false );
		wp_register_script( 'pcm-analytics', PCM_PLUGIN_URL . 'public/js/analytics.js', $debug_deps, $this->asset_version( 'public/js/analytics.js' ), false );
		wp_register_script(
			'pcm-consent',
			PCM_PLUGIN_URL . 'public/js/consent-manager.js',
			array_merge( $debug_deps, array( 'pcm-blocker', 'pcm-analytics' ) ),
			$this->asset_version( 'public/js/consent-manager.js' ),
			false // Head, not footer: the UI + unblocker must be ready early.
		);

		$config                 = $this->consent->get_client_config();
		$config['consentMode']  = ( new Google_Consent_Mode() )->client_config();
		$config['profile']      = $this->resolve_profile_config();
		$config['debug']        = $debug;
		$config['shouldRender'] = pcm_should_render_banner();

		wp_add_inline_script( 'pcm-consent', 'window.PCMConfig = ' . wp_json_encode( $config ) . ';', 'before' );

		if ( $debug ) {
			wp_enqueue_script( 'pcm-debug' );
		}
		wp_enqueue_script( 'pcm-consent' );
	}

	/**
	 * Jurisdiction profile for the current visitor.
	 *
	 * IMPORTANT cache note: country detection uses request headers, so on
	 * fully cached pages every visitor gets the profile cached first. Sites
	 * relying on per-country behaviour behind a full-page cache should
	 * either configure their cache to vary on the country header or use a
	 * single strict default profile (the plugin default).
	 *
	 * @return array
	 */
	private function resolve_profile_config() {
		$resolved = $this->geo->resolve_profile();
		$mode     = isset( $resolved['profile']['mode'] ) ? $resolved['profile']['mode'] : 'opt_in';
		return array(
			'key'            => $resolved['key'],
			'requireConsent' => ! empty( $resolved['profile']['require_consent'] ),
			'showRejectAll'  => ! empty( $resolved['profile']['show_reject_all'] ),
			'granular'       => ! empty( $resolved['profile']['granular'] ),
			'mode'           => in_array( $mode, array( 'opt_in', 'opt_out', 'notice_only' ), true ) ? $mode : 'opt_in',
		);
	}

	/**
	 * Whether any managed script template will be printed (assets are needed
	 * to unblock them even when the banner itself is disabled).
	 *
	 * @return bool
	 */
	private function has_managed_scripts() {
		if ( pcm_get_setting( 'ga4.enabled' ) || pcm_get_setting( 'gtm.enabled' )
			|| pcm_get_setting( 'clarity.enabled' ) || pcm_get_setting( 'cloudflare.enabled' ) ) {
			return true;
		}
		foreach ( (array) pcm_get_setting( 'custom_scripts', array() ) as $script ) {
			if ( ! empty( $script['enabled'] ) ) {
				return true;
			}
		}
		return (bool) pcm_get_setting( 'blocker.enabled', true );
	}

	/**
	 * CSS custom properties from the banner design settings.
	 *
	 * @return string
	 */
	private function banner_css_vars() {
		$banner = pcm_get_setting( 'banner', array() );

		$primary    = sanitize_hex_color( $banner['primary_color'] ?? '#1a73e8' ) ?: '#1a73e8';
		$text       = sanitize_hex_color( $banner['text_color'] ?? '#1f2937' ) ?: '#1f2937';
		$background = sanitize_hex_color( $banner['background_color'] ?? '#ffffff' ) ?: '#ffffff';

		// "Use theme colors": align the consent UI with the active theme's
		// palette (block themes). Explicit colors remain the fallback.
		if ( ! empty( $banner['use_theme_colors'] ) ) {
			$theme = pcm_theme_colors();
			if ( ! empty( $theme['primary'] ) ) {
				$primary = $theme['primary'];
			}
			if ( ! empty( $theme['text'] ) && 'light' === ( $banner['theme'] ?? 'light' ) ) {
				$text = $theme['text'];
			}
			if ( ! empty( $theme['background'] ) && 'light' === ( $banner['theme'] ?? 'light' ) ) {
				$background = $theme['background'];
			}
		}

		return sprintf(
			':root{--pcm-primary:%s;--pcm-text:%s;--pcm-bg:%s;--pcm-btn-text:%s;--pcm-font-size:%dpx;--pcm-radius:%dpx;}',
			$primary,
			$text,
			$background,
			sanitize_hex_color( $banner['button_text_color'] ?? '#ffffff' ) ?: '#ffffff',
			absint( $banner['font_size'] ?? 15 ),
			absint( $banner['border_radius'] ?? 8 )
		);
	}
}
