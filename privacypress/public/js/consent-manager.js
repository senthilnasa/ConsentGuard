/**
 * PCM consent manager — state, UI and public API.
 *
 * Reads configuration from window.PCMConfig (inlined by PHP), renders the
 * banner/preferences modal client-side (cache-safe), persists the decision
 * in a first-party cookie, unblocks scripts via PCMBlocker, updates Google
 * Consent Mode via PCMAnalytics, and records the decision server-side.
 *
 * Public API: window.PrivacyConsent
 *   .getConsent()            -> {category: bool, ...} | null
 *   .hasConsent('analytics') -> bool
 *   .onChange(cb)            -> unsubscribe fn
 *   .openPreferences() / .acceptAll() / .rejectAll() / .withdraw()
 *
 * DOM events on document:
 *   privacy_consent_ready, privacy_consent_changed,
 *   privacy_analytics_granted, privacy_analytics_denied,
 *   privacy_marketing_granted, privacy_marketing_denied
 *
 * @package PCM
 */
( function () {
	'use strict';

	var cfg = window.PCMConfig || {};
	var i18n = cfg.i18n || {};
	var listeners = [];
	var state = null;          // category => bool, or null before any decision.
	var implied = false;       // true while an opt-out profile implies consent without an explicit decision.
	var anonymousId = null;
	var ui = { banner: null, modal: null, reopen: null, lastFocus: null };

	/**
	 * Global Privacy Control: a browser-level opt-out-of-sale/share signal.
	 * When respected (default), it keeps the marketing category denied on
	 * blanket accepts and implied opt-out consent; an explicit marketing
	 * toggle in the preferences modal still wins.
	 */
	function gpcActive() {
		return cfg.respectGpc !== false && window.navigator && navigator.globalPrivacyControl === true;
	}

	function log() {
		if ( window.PCMDebug ) {
			window.PCMDebug.log.apply( window.PCMDebug, arguments );
		}
	}

	/* ------------------------------------------------------------------ *
	 * Cookie persistence
	 * ------------------------------------------------------------------ */

	function readCookie() {
		var match = document.cookie.match( new RegExp( '(?:^|;\\s*)' + cfg.cookieName + '=([^;]*)' ) );
		if ( ! match ) {
			return null;
		}
		try {
			return JSON.parse( decodeURIComponent( match[ 1 ] ) );
		} catch ( e ) {
			return null;
		}
	}

	function writeCookie( data ) {
		var days = cfg.cookieExpiry || 180;
		var expires = new Date( Date.now() + days * 864e5 ).toUTCString();
		var secure = window.location.protocol === 'https:' ? '; Secure' : '';
		document.cookie = cfg.cookieName + '=' + encodeURIComponent( JSON.stringify( data ) ) +
			'; expires=' + expires + '; path=/; SameSite=Lax' + secure;
	}

	function uuid() {
		if ( window.crypto && crypto.randomUUID ) {
			return crypto.randomUUID();
		}
		// RFC4122-ish fallback for older browsers.
		return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace( /[xy]/g, function ( c ) {
			var r = ( Math.random() * 16 ) | 0;
			return ( c === 'x' ? r : ( r & 0x3 ) | 0x8 ).toString( 16 );
		} );
	}

	function loadState() {
		var data = readCookie();
		if ( ! data || ! data.categories ) {
			return null;
		}
		anonymousId = data.id || null;
		// A changed consent version invalidates the stored decision.
		if ( cfg.repromptOnChange && data.version !== cfg.consentVersion ) {
			log( 'Consent version changed (' + data.version + ' -> ' + cfg.consentVersion + '), re-prompting' );
			return null;
		}
		return normalize( data.categories );
	}

	/**
	 * Ensures every configured category has an explicit boolean; required
	 * categories are always true.
	 */
	function normalize( categories ) {
		var out = {};
		var slug;
		for ( slug in cfg.categories ) {
			if ( ! Object.prototype.hasOwnProperty.call( cfg.categories, slug ) ) {
				continue;
			}
			out[ slug ] = cfg.categories[ slug ].required ? true : categories[ slug ] === true;
		}
		return out;
	}

	/* ------------------------------------------------------------------ *
	 * Consent application
	 * ------------------------------------------------------------------ */

	function dispatch( name, detail ) {
		var event;
		try {
			event = new CustomEvent( name, { detail: detail } );
		} catch ( e ) {
			event = document.createEvent( 'CustomEvent' );
			event.initCustomEvent( name, false, false, detail );
		}
		document.dispatchEvent( event );
	}

	function applyConsent( previous ) {
		if ( ! state ) {
			return;
		}

		// 1. Google Consent Mode update (real state, never faked).
		if ( cfg.consentMode && cfg.consentMode.enabled && window.PCMAnalytics ) {
			window.PCMAnalytics.updateConsentMode( state, cfg.consentMode.signalMap || {} );
		}

		// 2. Execute blocked scripts for granted categories.
		if ( window.PCMBlocker ) {
			window.PCMBlocker.unblock( state );
		}

		// 3. Remove first-party tracking cookies for denied categories.
		if ( window.PCMAnalytics ) {
			window.PCMAnalytics.clearDeniedCookies( state );
			window.PCMAnalytics.syncWpConsentApi( state );
		}

		// 4. Notify listeners + DOM events.
		listeners.forEach( function ( cb ) {
			try {
				cb( getConsent() );
			} catch ( e ) {
				log( 'onChange listener error', e );
			}
		} );
		dispatch( 'privacy_consent_changed', getConsent() );
		[ 'analytics', 'marketing' ].forEach( function ( category ) {
			if ( ! ( category in state ) ) {
				return;
			}
			dispatch( 'privacy_' + category + '_' + ( state[ category ] ? 'granted' : 'denied' ), getConsent() );
			if ( window.PCMDebug ) {
				window.PCMDebug.state( category, state[ category ] );
			}
		} );

		// 5. A withdrawal of a previously granted category cannot un-run
		//    already executed scripts — reload so the denied state is clean.
		if ( previous ) {
			var needsReload = false;
			var slug;
			for ( slug in previous ) {
				if ( previous[ slug ] === true && state[ slug ] !== true ) {
					needsReload = true;
					break;
				}
			}
			if ( needsReload ) {
				log( 'Consent withdrawn for a running category — reloading for a clean state' );
				window.setTimeout( function () {
					window.location.reload();
				}, 150 );
			}
		}
	}

	function persist( action ) {
		if ( ! anonymousId ) {
			anonymousId = uuid();
		}
		writeCookie( {
			id: anonymousId,
			version: cfg.consentVersion,
			policy: cfg.policyVersion,
			ts: Math.floor( Date.now() / 1000 ),
			categories: state
		} );

		if ( ! cfg.storeRecords || ! cfg.restUrl || ! window.fetch ) {
			return;
		}
		try {
			fetch( cfg.restUrl, {
				method: 'POST',
				keepalive: true,
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify( {
					categories: state,
					anonymous_id: anonymousId,
					consent_version: cfg.consentVersion,
					policy_version: cfg.policyVersion,
					language: cfg.language || ( navigator.language || '' ),
					region: cfg.profile && cfg.profile.key ? cfg.profile.key : '',
					action: action
				} )
			} ).catch( function ( e ) {
				log( 'Consent record failed', e );
			} );
		} catch ( e ) {
			log( 'Consent record failed', e );
		}
	}

	function decide( categories, action ) {
		var previous = state ? normalize( state ) : null;
		implied = false;
		state = normalize( categories );
		persist( action );
		hideBanner();
		hideModal();
		showReopen();
		applyConsent( previous );
	}

	/* ------------------------------------------------------------------ *
	 * Public API
	 * ------------------------------------------------------------------ */

	function getConsent() {
		if ( ! state ) {
			return null;
		}
		var copy = {};
		var slug;
		for ( slug in state ) {
			if ( Object.prototype.hasOwnProperty.call( state, slug ) ) {
				copy[ slug ] = state[ slug ];
			}
		}
		return copy;
	}

	window.PrivacyConsent = {
		getConsent: getConsent,
		hasConsent: function ( category ) {
			if ( cfg.categories[ category ] && cfg.categories[ category ].required ) {
				return true;
			}
			return !! ( state && state[ category ] === true );
		},
		onChange: function ( cb ) {
			if ( typeof cb === 'function' ) {
				listeners.push( cb );
			}
			return function () {
				var idx = listeners.indexOf( cb );
				if ( idx !== -1 ) {
					listeners.splice( idx, 1 );
				}
			};
		},
		acceptAll: function () {
			var all = {};
			var slug;
			for ( slug in cfg.categories ) {
				if ( Object.prototype.hasOwnProperty.call( cfg.categories, slug ) ) {
					all[ slug ] = true;
				}
			}
			if ( gpcActive() && 'marketing' in all ) {
				all.marketing = false;
				log( 'Global Privacy Control detected — marketing stays denied on Accept All' );
			}
			decide( all, 'accept_all' );
		},
		rejectAll: function () {
			decide( {}, 'reject_all' ); // normalize() keeps required categories on.
		},
		withdraw: function () {
			decide( {}, 'withdraw' );
		},
		openPreferences: function () {
			showModal();
		},
		getAnonymousId: function () {
			return anonymousId;
		},
		isImplied: function () {
			return implied;
		},
		gpcDetected: function () {
			return gpcActive();
		}
	};

	/* ------------------------------------------------------------------ *
	 * UI — banner
	 * ------------------------------------------------------------------ */

	function el( tag, className, attrs ) {
		var node = document.createElement( tag );
		if ( className ) {
			node.className = className;
		}
		var key;
		for ( key in ( attrs || {} ) ) {
			if ( Object.prototype.hasOwnProperty.call( attrs, key ) ) {
				node.setAttribute( key, attrs[ key ] );
			}
		}
		return node;
	}

	function button( label, className, onClick ) {
		var b = el( 'button', 'pcm-btn ' + className, { type: 'button' } );
		b.textContent = label;
		b.addEventListener( 'click', onClick );
		return b;
	}

	function buildBanner() {
		var banner = cfg.banner || {};
		var wrap = el( 'div', 'pcm-banner pcm-pos-' + ( banner.position || 'bottom' ) +
			' pcm-layout-' + ( banner.layout || 'bar' ) +
			' pcm-theme-' + ( banner.theme || 'light' ) +
			' pcm-anim-' + ( banner.animation || 'slide' ), {
			role: 'dialog',
			'aria-modal': 'false',
			'aria-labelledby': 'pcm-banner-title',
			'aria-describedby': 'pcm-banner-message'
		} );

		var inner = el( 'div', 'pcm-banner-inner' );

		if ( banner.logo_url ) {
			var logo = el( 'img', 'pcm-logo', { src: banner.logo_url, alt: '' } );
			inner.appendChild( logo );
		}

		var title = el( 'h2', 'pcm-title', { id: 'pcm-banner-title' } );
		title.textContent = banner.title || '';
		inner.appendChild( title );

		var message = el( 'p', 'pcm-message', { id: 'pcm-banner-message' } );
		message.textContent = banner.message || '';
		inner.appendChild( message );

		var links = el( 'p', 'pcm-links' );
		if ( cfg.privacyUrl ) {
			var privacy = el( 'a', 'pcm-link', { href: cfg.privacyUrl } );
			privacy.textContent = i18n.privacyPolicy || 'Privacy Policy';
			links.appendChild( privacy );
		}
		if ( cfg.cookieUrl ) {
			var cookie = el( 'a', 'pcm-link', { href: cfg.cookieUrl } );
			cookie.textContent = i18n.cookiePolicy || 'Cookie Policy';
			links.appendChild( cookie );
		}
		if ( links.childNodes.length ) {
			inner.appendChild( links );
		}

		var actions = el( 'div', 'pcm-actions' );
		actions.appendChild( button( banner.accept_label || 'Accept All', 'pcm-btn-primary', function () {
			window.PrivacyConsent.acceptAll();
		} ) );
		var noticeOnly = cfg.profile && cfg.profile.mode === 'notice_only';
		if ( ! noticeOnly && banner.show_reject !== false && ( ! cfg.profile || cfg.profile.showRejectAll !== false ) ) {
			actions.appendChild( button( banner.reject_label || 'Reject All', 'pcm-btn-secondary', function () {
				window.PrivacyConsent.rejectAll();
			} ) );
		}
		actions.appendChild( button( banner.manage_label || 'Manage Preferences', 'pcm-btn-tertiary', function () {
			showModal();
		} ) );
		inner.appendChild( actions );

		if ( banner.show_close ) {
			var close = button( '×', 'pcm-close', function () {
				hideBanner();
				showReopen();
			} );
			close.setAttribute( 'aria-label', i18n.close || 'Close' );
			wrap.appendChild( close );
		}

		wrap.appendChild( inner );
		return wrap;
	}

	function showBanner() {
		if ( ! ui.banner ) {
			ui.banner = buildBanner();
			document.body.appendChild( ui.banner );
		}
		ui.banner.classList.add( 'pcm-visible' );
	}

	function hideBanner() {
		if ( ui.banner ) {
			ui.banner.classList.remove( 'pcm-visible' );
		}
	}

	/* ------------------------------------------------------------------ *
	 * UI — preferences modal (focus-trapped, ESC to close)
	 * ------------------------------------------------------------------ */

	function buildModal() {
		var banner = cfg.banner || {};
		var overlay = el( 'div', 'pcm-overlay pcm-theme-' + ( banner.theme || 'light' ), { tabindex: '-1' } );
		var modal = el( 'div', 'pcm-modal', {
			role: 'dialog',
			'aria-modal': 'true',
			'aria-labelledby': 'pcm-modal-title'
		} );

		var title = el( 'h2', 'pcm-title', { id: 'pcm-modal-title' } );
		title.textContent = i18n.preferencesTitle || 'Privacy Preferences';
		modal.appendChild( title );

		var list = el( 'div', 'pcm-categories' );
		var slug, category, row, head, label, always, toggle, input, slider, desc;

		for ( slug in cfg.categories ) {
			if ( ! Object.prototype.hasOwnProperty.call( cfg.categories, slug ) ) {
				continue;
			}
			category = cfg.categories[ slug ];

			row = el( 'div', 'pcm-category' );
			head = el( 'div', 'pcm-category-head' );

			label = el( 'span', 'pcm-category-label', { id: 'pcm-cat-' + slug } );
			label.textContent = category.label;
			head.appendChild( label );

			if ( category.required ) {
				always = el( 'span', 'pcm-always-active' );
				always.textContent = i18n.alwaysActive || 'Always Active';
				head.appendChild( always );
			} else {
				toggle = el( 'label', 'pcm-toggle' );
				input = el( 'input', '', {
					type: 'checkbox',
					'data-pcm-toggle': slug,
					'aria-labelledby': 'pcm-cat-' + slug
				} );
				input.checked = !! ( state && state[ slug ] );
				slider = el( 'span', 'pcm-slider', { 'aria-hidden': 'true' } );
				toggle.appendChild( input );
				toggle.appendChild( slider );
				head.appendChild( toggle );
			}
			row.appendChild( head );

			desc = el( 'p', 'pcm-category-desc' );
			desc.textContent = category.description;
			row.appendChild( desc );

			list.appendChild( row );
		}
		modal.appendChild( list );

		var actions = el( 'div', 'pcm-actions' );
		actions.appendChild( button( banner.save_label || 'Save Preferences', 'pcm-btn-primary', function () {
			var chosen = {};
			var inputs = modal.querySelectorAll( '[data-pcm-toggle]' );
			var i;
			for ( i = 0; i < inputs.length; i++ ) {
				chosen[ inputs[ i ].getAttribute( 'data-pcm-toggle' ) ] = inputs[ i ].checked;
			}
			decide( chosen, 'custom' );
		} ) );
		actions.appendChild( button( banner.accept_label || 'Accept All', 'pcm-btn-secondary', function () {
			window.PrivacyConsent.acceptAll();
		} ) );
		if ( banner.show_reject !== false ) {
			actions.appendChild( button( banner.reject_label || 'Reject All', 'pcm-btn-secondary', function () {
				window.PrivacyConsent.rejectAll();
			} ) );
		}
		modal.appendChild( actions );

		var close = button( '×', 'pcm-close', hideModal );
		close.setAttribute( 'aria-label', i18n.close || 'Close' );
		modal.appendChild( close );

		if ( anonymousId ) {
			var idNote = el( 'p', 'pcm-consent-id' );
			idNote.textContent = ( i18n.consentId || 'Consent ID' ) + ': ' + anonymousId;
			modal.appendChild( idNote );
		}

		overlay.appendChild( modal );

		overlay.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' || e.keyCode === 27 ) {
				hideModal();
				return;
			}
			if ( e.key !== 'Tab' && e.keyCode !== 9 ) {
				return;
			}
			// Focus trap.
			var focusable = modal.querySelectorAll( 'button, input, a[href]' );
			if ( ! focusable.length ) {
				return;
			}
			var first = focusable[ 0 ];
			var last = focusable[ focusable.length - 1 ];
			if ( e.shiftKey && document.activeElement === first ) {
				e.preventDefault();
				last.focus();
			} else if ( ! e.shiftKey && document.activeElement === last ) {
				e.preventDefault();
				first.focus();
			}
		} );

		overlay.addEventListener( 'click', function ( e ) {
			if ( e.target === overlay ) {
				hideModal();
			}
		} );

		return overlay;
	}

	function showModal() {
		hideBanner();
		ui.lastFocus = document.activeElement;
		if ( ui.modal ) {
			ui.modal.parentNode.removeChild( ui.modal );
		}
		ui.modal = buildModal(); // Rebuilt each time so toggles reflect state.
		document.body.appendChild( ui.modal );
		ui.modal.classList.add( 'pcm-visible' );
		var firstButton = ui.modal.querySelector( 'input, button' );
		if ( firstButton ) {
			firstButton.focus();
		}
	}

	function hideModal() {
		if ( ui.modal ) {
			ui.modal.classList.remove( 'pcm-visible' );
			if ( ui.modal.parentNode ) {
				ui.modal.parentNode.removeChild( ui.modal );
			}
			ui.modal = null;
		}
		if ( ( state === null || implied ) && cfg.shouldRender ) {
			showBanner(); // No explicit decision yet: banner returns.
		}
		if ( ui.lastFocus && ui.lastFocus.focus ) {
			ui.lastFocus.focus();
			ui.lastFocus = null;
		}
	}

	/* ------------------------------------------------------------------ *
	 * UI — reopen button
	 * ------------------------------------------------------------------ */

	function showReopen() {
		var banner = cfg.banner || {};
		if ( banner.reopen_button === false || ui.reopen ) {
			return;
		}
		ui.reopen = button( banner.reopen_label || 'Privacy Settings', 'pcm-reopen pcm-theme-' + ( banner.theme || 'light' ), showModal );
		ui.reopen.setAttribute( 'aria-haspopup', 'dialog' );
		document.body.appendChild( ui.reopen );
	}

	/* ------------------------------------------------------------------ *
	 * Boot
	 * ------------------------------------------------------------------ */

	function init() {
		state = loadState();
		log( 'Consent initialized' );

		var mode = cfg.profile && cfg.profile.mode ? cfg.profile.mode : 'opt_in';

		if ( state ) {
			applyConsent( null );
			if ( cfg.shouldRender ) {
				showReopen();
			}
		} else if ( 'opt_out' === mode || 'notice_only' === mode ) {
			// OneTrust-style opt-out jurisdictions: the administrator has
			// configured this region as "tracking allowed until the visitor
			// objects". Consent is implied (never recorded as explicit) and
			// the banner still shows so the visitor can opt out. GPC still
			// keeps marketing denied.
			var all = {};
			var slug;
			for ( slug in cfg.categories ) {
				if ( Object.prototype.hasOwnProperty.call( cfg.categories, slug ) ) {
					all[ slug ] = true;
				}
			}
			if ( gpcActive() && 'marketing' in all ) {
				all.marketing = false;
				log( 'Global Privacy Control detected — marketing denied despite opt-out profile' );
			}
			implied = true;
			state = normalize( all );
			log( 'Opt-out profile "' + ( cfg.profile.key || mode ) + '": implied consent applied until the visitor decides' );
			applyConsent( null );
			if ( cfg.shouldRender ) {
				showBanner();
			}
		} else if ( cfg.shouldRender ) {
			showBanner();
		}

		// Delegated reopen support: any element with .pcm-open-preferences
		// (e.g. a footer link added by the site owner) opens the modal.
		document.addEventListener( 'click', function ( e ) {
			var target = e.target && e.target.closest ? e.target.closest( '.pcm-open-preferences' ) : null;
			if ( target ) {
				e.preventDefault();
				showModal();
			}
		} );

		dispatch( 'privacy_consent_ready', getConsent() );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
