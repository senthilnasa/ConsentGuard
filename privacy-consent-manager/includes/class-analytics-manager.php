<?php
/**
 * Coordinates the built-in analytics integrations.
 *
 * @package PCM
 */

namespace PCM;

defined( 'ABSPATH' ) || exit;

/**
 * Instantiates each integration and prints their consent-blocked templates
 * at the right places in the document.
 */
class Analytics_Manager {

	/**
	 * Integrations keyed by id.
	 *
	 * @var array<string, object>
	 */
	private $integrations = array();

	/**
	 * Registers integrations and output hooks.
	 */
	public function register() {
		$this->integrations = array(
			'consent_mode' => new Google_Consent_Mode(),
			'ga4'          => new Google_Analytics(),
			'gtm'          => new Google_Tag_Manager(),
			'clarity'      => new Microsoft_Clarity(),
			'cloudflare'   => new Cloudflare_Analytics(),
		);

		if ( is_admin() ) {
			return;
		}

		// Consent Mode defaults must print before anything Google-related.
		add_action( 'wp_head', array( $this->integrations['consent_mode'], 'print_defaults' ), 1 );

		add_action( 'wp_head', array( $this, 'print_head_scripts' ), 20 );
		add_action( 'wp_body_open', array( $this, 'print_body_scripts' ), 5 );
	}

	/**
	 * Returns an integration instance.
	 *
	 * @param string $id Integration id.
	 * @return object|null
	 */
	public function get( $id ) {
		return isset( $this->integrations[ $id ] ) ? $this->integrations[ $id ] : null;
	}

	/**
	 * Returns the status of every integration for dashboards/scans.
	 *
	 * @return array<string, array{enabled: bool, configured: bool, category: string}>
	 */
	public function statuses() {
		$out = array();
		foreach ( array( 'ga4', 'gtm', 'clarity', 'cloudflare' ) as $id ) {
			$out[ $id ] = $this->integrations[ $id ]->status();
		}
		return $out;
	}

	/**
	 * Prints head-position templates.
	 */
	public function print_head_scripts() {
		if ( pcm_is_elementor_editor() ) {
			return;
		}
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- templates are built with escaped attributes by Script_Manager.
		echo $this->integrations['ga4']->render();
		echo $this->integrations['gtm']->render();
		echo $this->integrations['clarity']->render();
		// phpcs:enable
	}

	/**
	 * Prints body-position templates (GTM noscript, Cloudflare beacon).
	 */
	public function print_body_scripts() {
		if ( pcm_is_elementor_editor() ) {
			return;
		}
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->integrations['gtm']->render_noscript();
		echo $this->integrations['cloudflare']->render();
		// phpcs:enable
	}
}
