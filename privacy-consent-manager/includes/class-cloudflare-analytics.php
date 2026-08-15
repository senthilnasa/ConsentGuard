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
	 * Renders the beacon — blocked when consent is required, plain otherwise.
	 *
	 * @return string
	 */
	public function render() {
		$status = $this->status();
		$gate   = pcm()->module( 'script_manager' );
		if ( ! $gate->should_output( $status['configured'], self::ID, $status['category'] ) ) {
			return '';
		}

		$src  = 'https://static.cloudflareinsights.com/beacon.min.js';
		$json = wp_json_encode( array( 'token' => $status['id'] ) );

		if ( $status['require_consent'] ) {
			return Script_Manager::blocked_external(
				$src,
				self::ID,
				$status['category'],
				array(
					'defer'     => true,
					'cf-beacon' => $json,
				)
			);
		}

		// Admin configured this cookieless beacon as consent-exempt.
		// Add it to the blocker allowlist implicitly via the data-pcm marker
		// so the auto-blocker does not re-block our own output.
		return sprintf(
			'<script data-pcm-id="%s" defer src="%s" data-cf-beacon="%s"></script>' . "\n",
			esc_attr( self::ID ),
			esc_url( $src ),
			esc_attr( $json )
		);
	}
}
