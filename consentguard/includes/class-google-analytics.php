<?php
/**
 * Google Analytics 4 integration.
 *
 * @package PCM
 */

namespace PCM;

defined( 'ABSPATH' ) || exit;

/**
 * Emits gtag.js for GA4 in consent-blocked form. The tag executes only
 * after the configured category is granted, and initializes exactly once
 * (guarded both here and in consent-manager.js).
 */
class Google_Analytics {

	const ID = 'ga4';

	/**
	 * Integration status.
	 *
	 * @return array
	 */
	public function status() {
		$enabled = (bool) pcm_get_setting( 'ga4.enabled', false );
		$mid     = (string) pcm_get_setting( 'ga4.measurement_id', '' );
		return array(
			'enabled'    => $enabled,
			'configured' => $enabled && '' !== $mid,
			'category'   => (string) pcm_get_setting( 'ga4.category', 'analytics' ),
			'id'         => $mid,
		);
	}

	/**
	 * Renders the blocked GA4 template.
	 *
	 * @return string
	 */
	public function render() {
		$status = $this->status();
		$gate   = pcm()->module( 'script_manager' );
		if ( ! $gate->should_output( $status['configured'], self::ID, $status['category'] ) ) {
			return '';
		}

		$mid      = $status['id'];
		$category = $status['category'];

		// Duplicate guard: window.__pcmGa4Loaded prevents double init even if
		// another copy of the snippet exists on the page.
		$config = 'window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}'
			. 'if(!window.__pcmGa4Loaded){window.__pcmGa4Loaded=true;'
			. "gtag('js',new Date());"
			. sprintf( "gtag('config',%s,{anonymize_ip:%s});", wp_json_encode( $mid ), pcm_get_setting( 'ga4.anonymize_ip', true ) ? 'true' : 'false' )
			. '}';

		$out  = Script_Manager::blocked_external(
			'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode( $mid ),
			self::ID . '-loader',
			$category,
			array( 'async' => true )
		);
		$out .= Script_Manager::blocked_inline( $config, self::ID, $category );
		return $out;
	}
}
