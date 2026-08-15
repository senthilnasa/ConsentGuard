/**
 * PCM debug logger.
 *
 * Only enqueued when Debug Mode is on. Other modules call
 * window.PCMDebug.log() guarded by an existence check, so this file being
 * absent in production is safe and free.
 *
 * @package PCM
 */
( function () {
	'use strict';

	window.PCMDebug = {
		log: function () {
			var args = Array.prototype.slice.call( arguments );
			args.unshift( '[PCM]' );
			// eslint-disable-next-line no-console
			if ( window.console && console.log ) {
				console.log.apply( console, args );
			}
		},
		state: function ( category, granted ) {
			this.log(
				category.charAt( 0 ).toUpperCase() + category.slice( 1 ) +
				' consent: ' + ( granted ? 'GRANTED' : 'DENIED' )
			);
		}
	};

	window.PCMDebug.log( 'Debug mode active' );
}() );
