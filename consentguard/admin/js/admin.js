/**
 * ConsentGuard — admin behaviour.
 *
 * @package PCM
 */
( function () {
	'use strict';

	// Persist dismissal of the legal notice.
	document.addEventListener( 'click', function ( e ) {
		var notice = e.target && e.target.closest ? e.target.closest( '[data-pcm-notice="legal"]' ) : null;
		if ( ! notice || ! e.target.classList.contains( 'notice-dismiss' ) ) {
			return;
		}
		if ( ! window.PCMAdmin || ! window.fetch ) {
			return;
		}
		var body = new FormData();
		body.append( 'action', 'pcm_dismiss_notice' );
		body.append( 'nonce', window.PCMAdmin.nonce );
		fetch( window.PCMAdmin.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } );
	} );
}() );
