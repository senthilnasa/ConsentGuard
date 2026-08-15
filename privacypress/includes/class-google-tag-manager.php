<?php
/**
 * Google Tag Manager integration.
 *
 * @package PCM
 */

namespace PCM;

defined( 'ABSPATH' ) || exit;

/**
 * Emits the GTM container in consent-blocked form. GTM can itself load
 * further third-party tags; the admin UI warns that every tag inside the
 * container must also respect the site's consent configuration (Consent
 * Mode signals are forwarded so consent-aware tags behave correctly).
 */
class Google_Tag_Manager {

	const ID = 'gtm';

	/**
	 * Integration status.
	 *
	 * @return array
	 */
	public function status() {
		$enabled = (bool) pcm_get_setting( 'gtm.enabled', false );
		$cid     = (string) pcm_get_setting( 'gtm.container_id', '' );
		return array(
			'enabled'    => $enabled,
			'configured' => $enabled && '' !== $cid,
			'category'   => (string) pcm_get_setting( 'gtm.category', 'marketing' ),
			'id'         => $cid,
		);
	}

	/**
	 * Renders the blocked GTM loader.
	 *
	 * @return string
	 */
	public function render() {
		$status = $this->status();
		$gate   = pcm()->module( 'script_manager' );
		if ( ! $gate->should_output( $status['configured'], self::ID, $status['category'] ) ) {
			return '';
		}

		$js = 'if(!window.__pcmGtmLoaded){window.__pcmGtmLoaded=true;'
			. '(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({"gtm.start":new Date().getTime(),event:"gtm.js"});'
			. 'var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!="dataLayer"?"&l="+l:"";'
			. 'j.async=true;j.src="https://www.googletagmanager.com/gtm.js?id="+i+dl;'
			. 'f.parentNode.insertBefore(j,f);'
			. sprintf( '})(window,document,"script","dataLayer",%s);', wp_json_encode( $status['id'] ) )
			. '}';

		return Script_Manager::blocked_inline( $js, self::ID, $status['category'] );
	}

	/**
	 * GTM noscript iframe. Deliberately NOT emitted before consent: an
	 * iframe cannot be consent-gated client-side without JavaScript, and
	 * without JavaScript no consent can be granted either.
	 *
	 * @return string
	 */
	public function render_noscript() {
		return '';
	}
}
