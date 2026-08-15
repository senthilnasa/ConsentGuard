/**
 * PCM script unblocker.
 *
 * Finds <script type="text/plain"> templates rendered by the PHP layer
 * (both managed integrations and auto-blocked third-party scripts) and
 * executes each exactly once when its consent category is granted.
 *
 * @package PCM
 */
( function () {
	'use strict';

	var executed = ( typeof WeakSet !== 'undefined' ) ? new WeakSet() : null;
	var executedFallback = [];

	function log() {
		if ( window.PCMDebug ) {
			window.PCMDebug.log.apply( window.PCMDebug, arguments );
		}
	}

	function wasExecuted( node ) {
		if ( executed ) {
			return executed.has( node );
		}
		return executedFallback.indexOf( node ) !== -1;
	}

	function markExecuted( node ) {
		if ( executed ) {
			executed.add( node );
		} else {
			executedFallback.push( node );
		}
	}

	/**
	 * Re-creates a blocked script node as a live one. Cloning is required:
	 * changing "type" on an existing script element does not execute it.
	 */
	function activate( node ) {
		if ( wasExecuted( node ) ) {
			return;
		}
		markExecuted( node );

		var script = document.createElement( 'script' );
		var src = node.getAttribute( 'data-pcm-src' ) || node.getAttribute( 'src' );
		var i, attr;

		// Restore stashed attributes (data-pcm-attr-defer -> defer, etc.).
		for ( i = 0; i < node.attributes.length; i++ ) {
			attr = node.attributes[ i ];
			if ( attr.name.indexOf( 'data-pcm-attr-' ) === 0 ) {
				script.setAttribute( attr.name.replace( 'data-pcm-attr-', '' ), attr.value === '' ? '' : attr.value );
			}
		}
		script.setAttribute( 'data-pcm-activated', '1' );

		if ( src ) {
			script.src = src;
			if ( ! script.hasAttribute( 'defer' ) && ! script.hasAttribute( 'async' ) ) {
				script.async = true;
			}
		} else {
			script.text = node.textContent;
		}

		node.parentNode.insertBefore( script, node.nextSibling );
		log( ( node.getAttribute( 'data-pcm-id' ) || src || 'inline script' ) + ' initialized' );
	}

	/**
	 * Executes every blocked script whose category is granted.
	 *
	 * @param {Object} consent category => boolean map.
	 */
	function unblock( consent ) {
		var nodes = document.querySelectorAll(
			'script[type="text/plain"][data-pcm-category]'
		);
		var i, node, category;

		for ( i = 0; i < nodes.length; i++ ) {
			node = nodes[ i ];
			category = node.getAttribute( 'data-pcm-category' );
			if ( consent[ category ] === true ) {
				activate( node );
			} else if ( window.PCMDebug && ! wasExecuted( node ) ) {
				log( ( node.getAttribute( 'data-pcm-id' ) || 'script' ) + ' blocked (' + category + ')' );
			}
		}
	}

	window.PCMBlocker = {
		unblock: unblock
	};
}() );
