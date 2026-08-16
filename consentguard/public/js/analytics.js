/**
 * PCM analytics bridge.
 *
 * - Sends Google Consent Mode v2 updates that mirror the visitor's actual
 *   choice (denied is sent as denied — never faked).
 * - Clears first-party tracking cookies for withdrawn categories.
 *
 * @package PCM
 */
( function () {
	'use strict';

	function log() {
		if ( window.PCMDebug ) {
			window.PCMDebug.log.apply( window.PCMDebug, arguments );
		}
	}

	/**
	 * Pushes gtag('consent','update') derived from the category map.
	 *
	 * @param {Object} consent   category => boolean.
	 * @param {Object} signalMap google signal => category.
	 */
	function updateConsentMode( consent, signalMap ) {
		var update = {};
		var signal, category;

		for ( signal in signalMap ) {
			if ( ! Object.prototype.hasOwnProperty.call( signalMap, signal ) ) {
				continue;
			}
			category = signalMap[ signal ];
			update[ signal ] = ( category === 'necessary' || consent[ category ] === true ) ? 'granted' : 'denied';
		}

		window.dataLayer = window.dataLayer || [];
		function gtag() { window.dataLayer.push( arguments ); }
		gtag( 'consent', 'update', update );
		log( 'Consent Mode update', update );
	}

	/**
	 * First-party cookies set by trackers, grouped by consent category.
	 * Deleted when the category is (or becomes) denied. Third-party services
	 * may retain server-side data; deleting these cookies does not claim
	 * otherwise.
	 */
	var CATEGORY_COOKIES = {
		analytics: [ /^_ga($|_)/, /^_gid$/, /^_gat/, /^_clck$/, /^_clsk$/, /^CLID$/, /^MUID$/, /^_cfa/ ],
		marketing: [ /^_fbp$/, /^_fbc$/, /^_gcl_/, /^fr$/, /^IDE$/ ]
	};

	function deleteCookie( name ) {
		var host = window.location.hostname;
		var domains = [ '', host, '.' + host ];
		// Also try the registrable parent domain (e.g. .example.com for www).
		var parts = host.split( '.' );
		if ( parts.length > 2 ) {
			domains.push( '.' + parts.slice( -2 ).join( '.' ) );
		}
		domains.forEach( function ( domain ) {
			document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/' +
				( domain ? '; domain=' + domain : '' );
		} );
	}

	/**
	 * Removes first-party tracking cookies for every denied category.
	 *
	 * @param {Object} consent category => boolean.
	 */
	function clearDeniedCookies( consent ) {
		var all = document.cookie ? document.cookie.split( ';' ) : [];
		var category, patterns, i, j, name;

		for ( category in CATEGORY_COOKIES ) {
			if ( consent[ category ] === true ) {
				continue;
			}
			patterns = CATEGORY_COOKIES[ category ];
			for ( i = 0; i < all.length; i++ ) {
				name = all[ i ].split( '=' )[ 0 ].replace( /^\s+/, '' );
				for ( j = 0; j < patterns.length; j++ ) {
					if ( patterns[ j ].test( name ) ) {
						deleteCookie( name );
						log( 'Removed cookie ' + name + ' (' + category + ' denied)' );
						break;
					}
				}
			}
		}
	}

	/**
	 * Bridges consent to the WordPress Consent API (wp-consent-api plugin)
	 * so other consent-aware plugins see the same decision.
	 *
	 * @param {Object} consent category => boolean.
	 */
	function syncWpConsentApi( consent ) {
		if ( typeof window.wp_set_consent !== 'function' ) {
			return;
		}
		var map = {
			functional: 'functional',
			analytics: 'statistics',
			marketing: 'marketing',
			preferences: 'preferences'
		};
		var category;
		for ( category in map ) {
			if ( Object.prototype.hasOwnProperty.call( map, category ) && category in consent ) {
				window.wp_set_consent( map[ category ], consent[ category ] ? 'allow' : 'deny' );
			}
		}
		log( 'WP Consent API synchronized' );
	}

	window.PCMAnalytics = {
		updateConsentMode: updateConsentMode,
		clearDeniedCookies: clearDeniedCookies,
		syncWpConsentApi: syncWpConsentApi
	};
}() );
