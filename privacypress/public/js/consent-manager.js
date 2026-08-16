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
 *   .isImplied() / .gpcDetected() / .getAnonymousId()
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
	 * UI — helpers
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

	function themeClass() {
		return 'pcm-theme-' + ( ( cfg.banner && cfg.banner.theme ) || 'light' );
	}

	/* ------------------------------------------------------------------ *
	 * UI — banner
	 * ------------------------------------------------------------------ */

	function buildBanner() {
		var banner = cfg.banner || {};
		var wrap = el( 'div', 'pcm-banner pcm-pos-' + ( banner.position || 'bottom' ) +
			' pcm-layout-' + ( banner.layout || 'bar' ) +
			' ' + themeClass() +
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
	 * UI — preferences modal (accordion detail view, focus-trapped)
	 * ------------------------------------------------------------------ */

	function buildIntro() {
		var banner = cfg.banner || {};
		var intro = el( 'div', 'pcm-intro' );
		var text = banner.preferences_intro || banner.message || '';
		var p = el( 'p', 'pcm-intro-text', { id: 'pcm-intro-text' } );
		p.textContent = text;
		intro.appendChild( p );

		if ( text.length > 180 ) {
			p.classList.add( 'pcm-clamped' );
			var more = el( 'button', 'pcm-show-more', {
				type: 'button',
				'aria-expanded': 'false',
				'aria-controls': 'pcm-intro-text'
			} );
			more.textContent = i18n.showMore || 'Show more';
			more.addEventListener( 'click', function () {
				var expanded = p.classList.toggle( 'pcm-clamped' ) === false;
				more.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
				more.textContent = expanded ? ( i18n.showLess || 'Show less' ) : ( i18n.showMore || 'Show more' );
			} );
			intro.appendChild( more );
		}
		return intro;
	}

	function buildCookieTable( slug ) {
		var table = el( 'div', 'pcm-audit-table' );
		var rows = ( cfg.cookies && cfg.cookies[ slug ] ) || [];
		var services = ( cfg.services && cfg.services[ slug ] ) || [];
		var i, row, list, fields, f, li, k, v;

		for ( i = 0; i < rows.length; i++ ) {
			row = rows[ i ];
			list = el( 'ul', 'pcm-cookie-row' );
			fields = [
				[ i18n.cookie || 'Cookie', row.name || '' ],
				[ i18n.duration || 'Duration', row.duration || '-' ],
				[ i18n.description || 'Description', row.description || '-' ]
			];
			for ( f = 0; f < fields.length; f++ ) {
				li = el( 'li' );
				k = el( 'div', 'pcm-cookie-key' );
				k.textContent = fields[ f ][ 0 ];
				v = el( 'div', 'pcm-cookie-val' );
				v.textContent = fields[ f ][ 1 ];
				li.appendChild( k );
				li.appendChild( v );
				list.appendChild( li );
			}
			table.appendChild( list );
		}

		if ( services.length ) {
			var svc = el( 'p', 'pcm-services-line' );
			svc.textContent = ( i18n.managedServices || 'Managed services' ) + ': ' + services.join( ', ' );
			table.appendChild( svc );
		}

		if ( ! rows.length && ! services.length ) {
			var none = el( 'p', 'pcm-services-line' );
			none.textContent = i18n.noCookies || 'No cookies to display for this category.';
			table.appendChild( none );
		}
		return table;
	}

	function buildAccordion( slug, category, first ) {
		var acc = el( 'div', 'pcm-accordion' + ( first ? ' pcm-open' : '' ), { id: 'pcm-acc-' + slug } );
		var head = el( 'div', 'pcm-accordion-head' );
		var bodyId = 'pcm-acc-body-' + slug;

		var expander = el( 'button', 'pcm-accordion-btn', {
			type: 'button',
			'aria-expanded': first ? 'true' : 'false',
			'aria-controls': bodyId
		} );
		expander.setAttribute( 'aria-label', ( i18n.expandCategory || 'Show cookie details for' ) + ' ' + category.label );
		var chevron = el( 'span', 'pcm-chevron', { 'aria-hidden': 'true' } );
		var labelSpan = el( 'span', 'pcm-category-label', { id: 'pcm-cat-' + slug } );
		labelSpan.textContent = category.label;
		expander.appendChild( chevron );
		expander.appendChild( labelSpan );
		head.appendChild( expander );

		if ( category.required ) {
			var always = el( 'span', 'pcm-always-active' );
			always.textContent = i18n.alwaysActive || 'Always Active';
			head.appendChild( always );
		} else {
			var toggle = el( 'label', 'pcm-toggle' );
			var input = el( 'input', '', {
				type: 'checkbox',
				role: 'switch',
				'data-pcm-toggle': slug,
				'aria-labelledby': 'pcm-cat-' + slug
			} );
			input.checked = !! ( state && state[ slug ] );
			var slider = el( 'span', 'pcm-slider', { 'aria-hidden': 'true' } );
			toggle.appendChild( input );
			toggle.appendChild( slider );
			head.appendChild( toggle );
		}
		acc.appendChild( head );

		var desc = el( 'p', 'pcm-category-desc' );
		desc.textContent = category.description;
		acc.appendChild( desc );

		var body = el( 'div', 'pcm-accordion-body', { id: bodyId } );
		body.appendChild( buildCookieTable( slug ) );
		acc.appendChild( body );

		expander.addEventListener( 'click', function () {
			var open = acc.classList.toggle( 'pcm-open' );
			expander.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		} );

		return acc;
	}

	function buildModal() {
		var banner = cfg.banner || {};
		var overlay = el( 'div', 'pcm-overlay ' + themeClass(), { tabindex: '-1' } );
		var modal = el( 'div', 'pcm-modal', {
			role: 'dialog',
			'aria-modal': 'true',
			'aria-labelledby': 'pcm-modal-title'
		} );

		// Header.
		var header = el( 'div', 'pcm-modal-header' );
		if ( banner.logo_url ) {
			var logo = el( 'img', 'pcm-modal-logo', { src: banner.logo_url, alt: '' } );
			header.appendChild( logo );
		}
		var title = el( 'h2', 'pcm-title', { id: 'pcm-modal-title' } );
		title.textContent = i18n.preferencesTitle || 'Customise Consent Preferences';
		header.appendChild( title );
		var close = button( '×', 'pcm-close', hideModal );
		close.setAttribute( 'aria-label', i18n.close || 'Close' );
		header.appendChild( close );
		modal.appendChild( header );

		// Scrollable body.
		var body = el( 'div', 'pcm-modal-body' );
		body.appendChild( buildIntro() );

		var list = el( 'div', 'pcm-accordions' );
		var slug, first = true;
		for ( slug in cfg.categories ) {
			if ( ! Object.prototype.hasOwnProperty.call( cfg.categories, slug ) ) {
				continue;
			}
			list.appendChild( buildAccordion( slug, cfg.categories[ slug ], first ) );
			first = false;
		}
		body.appendChild( list );
		modal.appendChild( body );

		// Footer.
		var footer = el( 'div', 'pcm-modal-footer' );
		var actions = el( 'div', 'pcm-actions' );
		var noticeOnly = cfg.profile && cfg.profile.mode === 'notice_only';
		if ( banner.show_reject !== false && ! noticeOnly ) {
			actions.appendChild( button( banner.reject_label || 'Reject All', 'pcm-btn-secondary', function () {
				window.PrivacyConsent.rejectAll();
			} ) );
		}
		actions.appendChild( button( banner.save_label || 'Save Preferences', 'pcm-btn-primary', function () {
			var chosen = {};
			var inputs = modal.querySelectorAll( '[data-pcm-toggle]' );
			var i;
			for ( i = 0; i < inputs.length; i++ ) {
				chosen[ inputs[ i ].getAttribute( 'data-pcm-toggle' ) ] = inputs[ i ].checked;
			}
			decide( chosen, 'custom' );
		} ) );
		actions.appendChild( button( banner.accept_label || 'Accept All', 'pcm-btn-secondary pcm-btn-accent', function () {
			window.PrivacyConsent.acceptAll();
		} ) );
		footer.appendChild( actions );

		if ( anonymousId ) {
			var idNote = el( 'p', 'pcm-consent-id' );
			idNote.textContent = ( i18n.consentId || 'Consent ID' ) + ': ' + anonymousId;
			footer.appendChild( idNote );
		}
		modal.appendChild( footer );

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
			var firstNode = focusable[ 0 ];
			var last = focusable[ focusable.length - 1 ];
			if ( e.shiftKey && document.activeElement === firstNode ) {
				e.preventDefault();
				last.focus();
			} else if ( ! e.shiftKey && document.activeElement === last ) {
				e.preventDefault();
				firstNode.focus();
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
	 * UI — floating revisit widget (icon button, draggable)
	 * ------------------------------------------------------------------ */

	var REOPEN_POS_KEY = 'pcmReopenPos';

	function defaultReopenIcon() {
		var span = el( 'span', 'pcm-reopen-icon', { 'aria-hidden': 'true' } );
		// Inline cookie glyph — no external asset needed.
		span.innerHTML = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" xmlns="http://www.w3.org/2000/svg">' +
			'<path d="M21.9 11.1a1 1 0 0 0-1.1-.8 3 3 0 0 1-3.3-2.6 1 1 0 0 0-.9-.9A3 3 0 0 1 14 3.6a1 1 0 0 0-.9-1.2A10 10 0 1 0 22 12c0-.3 0-.6-.1-.9Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>' +
			'<circle cx="8.5" cy="10" r="1.2" fill="currentColor"/><circle cx="12" cy="15.5" r="1.2" fill="currentColor"/><circle cx="15.8" cy="11.5" r="1.2" fill="currentColor"/><circle cx="8.8" cy="15.2" r="0.9" fill="currentColor"/>' +
			'</svg>';
		return span;
	}

	function savedReopenPosition() {
		try {
			var raw = window.localStorage.getItem( REOPEN_POS_KEY );
			if ( ! raw ) {
				return null;
			}
			var pos = JSON.parse( raw );
			if ( typeof pos.x === 'number' && typeof pos.y === 'number' ) {
				return pos;
			}
		} catch ( e ) { /* Private mode etc. */ }
		return null;
	}

	function clampReopen( value, max ) {
		return Math.min( Math.max( value, 8 ), max );
	}

	function applyReopenPosition( node, pos ) {
		node.style.left = clampReopen( pos.x, window.innerWidth - node.offsetWidth - 8 ) + 'px';
		node.style.top = clampReopen( pos.y, window.innerHeight - node.offsetHeight - 8 ) + 'px';
		node.style.right = 'auto';
		node.style.bottom = 'auto';
	}

	function makeDraggable( node ) {
		var DRAG_THRESHOLD = 6; // px of travel before a press counts as a drag.
		var dragging = false;
		var moved = false;
		var startX = 0;
		var startY = 0;
		var offsetX = 0;
		var offsetY = 0;

		node.addEventListener( 'pointerdown', function ( e ) {
			dragging = true;
			moved = false;
			startX = e.clientX;
			startY = e.clientY;
			var rect = node.getBoundingClientRect();
			offsetX = e.clientX - rect.left;
			offsetY = e.clientY - rect.top;
			// Deliberately NO setPointerCapture here: in Chrome "click" is a
			// PointerEvent, so capturing on pointerdown would retarget the
			// click to this wrapper and the button would never receive it.
			// Capture starts only once the press becomes a real drag.
		} );

		node.addEventListener( 'pointermove', function ( e ) {
			if ( ! dragging ) {
				return;
			}
			// A press only becomes a drag after real travel; jittery clicks
			// (a few sub-threshold pointermoves) must stay clicks.
			var dx = e.clientX - startX;
			var dy = e.clientY - startY;
			if ( ! moved && ( dx * dx + dy * dy ) < DRAG_THRESHOLD * DRAG_THRESHOLD ) {
				return;
			}
			if ( ! moved && node.setPointerCapture && e.pointerId !== undefined ) {
				try {
					node.setPointerCapture( e.pointerId );
				} catch ( err ) { /* older browsers */ }
			}
			moved = true;
			node.classList.add( 'pcm-dragging' );
			applyReopenPosition( node, { x: e.clientX - offsetX, y: e.clientY - offsetY } );
		} );

		node.addEventListener( 'pointerup', function () {
			if ( ! dragging ) {
				return;
			}
			dragging = false;
			node.classList.remove( 'pcm-dragging' );
			if ( moved ) {
				try {
					window.localStorage.setItem( REOPEN_POS_KEY, JSON.stringify( {
						x: parseInt( node.style.left, 10 ) || 8,
						y: parseInt( node.style.top, 10 ) || 8
					} ) );
				} catch ( e ) { /* ignore */ }
				// Swallow only the click that concludes a real drag.
				var swallow = function ( ev ) {
					ev.stopPropagation();
					ev.preventDefault();
				};
				node.addEventListener( 'click', swallow, true );
				window.setTimeout( function () {
					node.removeEventListener( 'click', swallow, true );
				}, 0 );
			}
		} );

		node.addEventListener( 'pointercancel', function () {
			dragging = false;
			node.classList.remove( 'pcm-dragging' );
		} );
	}

	function showReopen() {
		var banner = cfg.banner || {};
		if ( banner.reopen_button === false || ui.reopen ) {
			return;
		}

		var wrap = el(
			'div',
			'pcm-reopen pcm-reopen-' + ( banner.reopen_position || 'bottom-left' ) + ' ' + themeClass(),
			{ 'data-pcm-tooltip': banner.reopen_label || 'Privacy Settings' }
		);

		var btn = el( 'button', 'pcm-reopen-btn', {
			type: 'button',
			'aria-haspopup': 'dialog',
			'aria-label': banner.reopen_label || 'Privacy Settings'
		} );
		if ( banner.reopen_icon_url ) {
			btn.appendChild( el( 'img', 'pcm-reopen-img', { src: banner.reopen_icon_url, alt: '' } ) );
		} else {
			btn.appendChild( defaultReopenIcon() );
		}
		btn.addEventListener( 'click', showModal );
		wrap.appendChild( btn );

		var saved = savedReopenPosition();
		document.body.appendChild( wrap );
		if ( saved ) {
			applyReopenPosition( wrap, saved );
		}
		if ( banner.reopen_draggable !== false ) {
			makeDraggable( wrap );
		}

		ui.reopen = wrap;
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
