<?php
/**
 * Microsoft Clarity integration.
 *
 * @package PCM
 */

namespace PCM;

defined( 'ABSPATH' ) || exit;

/**
 * Emits the Clarity snippet in consent-blocked form with a duplicate guard:
 * if window.clarity already exists (e.g. the official Clarity plugin also
 * injected it), this snippet does nothing and logs in debug mode.
 */
class Microsoft_Clarity {

	const ID = 'clarity';

	/**
	 * Integration status.
	 *
	 * @return array
	 */
	public function status() {
		$enabled = (bool) pcm_get_setting( 'clarity.enabled', false );
		$pid     = (string) pcm_get_setting( 'clarity.project_id', '' );
		return array(
			'enabled'    => $enabled,
			'configured' => $enabled && '' !== $pid,
			'category'   => (string) pcm_get_setting( 'clarity.category', 'analytics' ),
			'id'         => $pid,
		);
	}

	/**
	 * Renders the blocked Clarity template.
	 *
	 * @return string
	 */
	public function render() {
		$status = $this->status();
		$gate   = pcm()->module( 'script_manager' );
		if ( ! $gate->should_output( $status['configured'], self::ID, $status['category'] ) ) {
			return '';
		}

		$js = 'if(window.clarity||window.__pcmClarityLoaded){if(window.PCMDebug){PCMDebug.log("Clarity already present, skipping duplicate init");}}else{'
			. 'window.__pcmClarityLoaded=true;'
			. '(function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};'
			. 't=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;'
			. 'y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);'
			. sprintf( '})(window,document,"clarity","script",%s);', wp_json_encode( $status['id'] ) )
			. '}';

		return Script_Manager::blocked_inline( $js, self::ID, $status['category'] );
	}
}
