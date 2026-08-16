<?php
/**
 * Cloudflare Web Analytics integration.
 *
 * @package PCM
 */

namespace PCM;

defined( 'ABSPATH' ) || exit;

/**
 * Emits the Cloudflare Web Analytics beacon.
 *
 * This integration covers ONLY Cloudflare Web Analytics (the beacon script
 * from static.cloudflareinsights.com) — not other Cloudflare products such
 * as the CDN, Turnstile or Zaraz, which have different privacy properties.
 *
 * Because Cloudflare Web Analytics is cookieless, some sites legitimately
 * configure it as not requiring consent; that is an administrator decision
 * (require_consent setting), not an assumption this plugin makes.
 */
class Cloudflare_Analytics {

	const ID = 'cloudflare';

	/**
	 * Integration status.
	 *
	 * @return array
	 */
	public function status() {
		$enabled = (bool) pcm_get_setting( 'cloudflare.enabled', false );
		$token   = (string) pcm_get_setting( 'cloudflare.token', '' );
		return array(
			'enabled'         => $enabled,
			'configured'      => $enabled && '' !== $token,
			'category'        => (string) pcm_get_setting( 'cloudflare.category', 'analytics' ),
			'require_consent' => (bool) pcm_get_setting( 'cloudflare.require_consent', true ),
			'id'              => $token,
		);
	}

	/**
	 * Registers the properly-enqueued beacon for the consent-exempt case.
	 * (The consent-gated case renders as a blocked template in render().)
	 */
	public function register_hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_exempt' ) );
		add_filter( 'script_loader_tag', array( $this, 'add_beacon_attributes' ), 10, 2 );
	}

	/**
	 * Enqueues the beacon via the standard script API when the administrator
	 * marked this cookieless integration as not requiring consent.
	 */
	public function maybe_enqueue_exempt() {
		$status = $this->status();
		if ( ! $status['configured'] || $status['require_consent'] || pcm_is_elementor_editor() ) {
			return;
		}
		if ( ! pcm()->module( 'script_manager' )->should_output( true, self::ID, $status['category'] ) ) {
			return;
		}
		wp_enqueue_script(
			'pcm-cf-beacon',
			'https://static.cloudflareinsights.com/beacon.min.js',
			array(),
			null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- external beacon; Cloudflare manages its own cache busting.
			true
		);
	}

	/**
	 * Adds the defer + data-cf-beacon token attributes to the enqueued tag,
	 * and a data-pcm marker so the auto-blocker skips our own output.
	 *
	 * @param string $tag    Script tag HTML.
	 * @param string $handle Script handle.
	 * @return string
	 */
	public function add_beacon_attributes( $tag, $handle ) {
		if ( 'pcm-cf-beacon' !== $handle ) {
			return $tag;
		}
		$json = wp_json_encode( array( 'token' => (string) pcm_get_setting( 'cloudflare.token', '' ) ) );
		return str_replace(
			' src=',
			sprintf( ' defer data-pcm-id="%s" data-cf-beacon="%s" src=', esc_attr( self::ID ), esc_attr( $json ) ),
			$tag
		);
	}

	/**
	 * Renders the beacon in consent-blocked form when consent is required.
	 * The consent-exempt case is handled by maybe_enqueue_exempt() instead.
	 *
	 * @return string
	 */
	public function render() {
		$status = $this->status();
		if ( ! $status['require_consent'] ) {
			return '';
		}
		$gate = pcm()->module( 'script_manager' );
		if ( ! $gate->should_output( $status['configured'], self::ID, $status['category'] ) ) {
			return '';
		}

		return Script_Manager::blocked_external(
			'https://static.cloudflareinsights.com/beacon.min.js',
			self::ID,
			$status['category'],
			array(
				'defer'     => true,
				'cf-beacon' => wp_json_encode( array( 'token' => $status['id'] ) ),
			)
		);
	}
}
