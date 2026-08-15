<?php
/**
 * Google Consent Mode v2.
 *
 * @package PCM
 */

namespace PCM;

defined( 'ABSPATH' ) || exit;

/**
 * Prints Consent Mode v2 defaults before any Google tag can initialize and
 * exposes the category → consent-signal mapping used by the frontend to
 * send gtag('consent','update', ...) when the visitor decides.
 *
 * All signals default to "denied" except security_storage. Denied consent
 * is never converted to granted by this plugin — updates happen only from
 * an explicit visitor choice relayed by consent-manager.js.
 */
class Google_Consent_Mode {

	/**
	 * Maps Google consent signals to plugin consent categories.
	 *
	 * @return array<string,string> signal => category.
	 */
	public function signal_map() {
		/**
		 * Filters the Google Consent Mode signal → category mapping.
		 *
		 * @param array $map signal => category slug.
		 */
		return apply_filters(
			'pcm_consent_mode_map',
			array(
				'ad_storage'              => 'marketing',
				'ad_user_data'            => 'marketing',
				'ad_personalization'      => 'marketing',
				'analytics_storage'       => 'analytics',
				'functionality_storage'   => 'functional',
				'personalization_storage' => 'preferences',
				'security_storage'        => 'necessary',
			)
		);
	}

	/**
	 * Whether Consent Mode is active.
	 *
	 * @return bool
	 */
	public function enabled() {
		return (bool) pcm_get_setting( 'consent_mode.enabled', true );
	}

	/**
	 * Prints the default (denied) consent state. Runs at wp_head priority 1,
	 * unblocked, because it must execute before consent is given — it is the
	 * mechanism that keeps Google tags in denied mode.
	 */
	public function print_defaults() {
		if ( ! $this->enabled() || pcm_is_elementor_editor() ) {
			return;
		}

		$defaults = array();
		foreach ( $this->signal_map() as $signal => $category ) {
			$defaults[ $signal ] = 'necessary' === $category ? 'granted' : 'denied';
		}
		$defaults['wait_for_update'] = (int) pcm_get_setting( 'consent_mode.wait_for_update', 500 );

		$extra = '';
		if ( pcm_get_setting( 'consent_mode.ads_data_redaction', true ) ) {
			$extra .= "gtag('set','ads_data_redaction',true);";
		}
		if ( pcm_get_setting( 'consent_mode.url_passthrough', false ) ) {
			$extra .= "gtag('set','url_passthrough',true);";
		}

		printf(
			"<script data-pcm-id=\"consent-mode-default\">window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('consent','default',%s);%s</script>\n",
			wp_json_encode( $defaults ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON from internal data.
			$extra // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static internal JS.
		);
	}

	/**
	 * Client config for consent-manager.js.
	 *
	 * @return array
	 */
	public function client_config() {
		return array(
			'enabled'   => $this->enabled(),
			'signalMap' => $this->signal_map(),
		);
	}
}
