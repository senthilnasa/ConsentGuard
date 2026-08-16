/**
 * Jest tests for the frontend consent stack
 * (script-blocker.js + analytics.js + consent-manager.js) under jsdom.
 *
 * @package PCM
 */

'use strict';

const CONFIG = {
	cookieName: 'pcm_consent',
	cookieExpiry: 180,
	consentVersion: '1.0',
	policyVersion: '1.0',
	repromptOnChange: true,
	storeRecords: false,
	shouldRender: true,
	restUrl: '',
	privacyUrl: 'https://example.test/privacy',
	cookieUrl: '',
	language: 'en_US',
	profile: { key: 'gdpr', requireConsent: true, showRejectAll: true, granular: true },
	consentMode: {
		enabled: true,
		signalMap: {
			ad_storage: 'marketing',
			ad_user_data: 'marketing',
			ad_personalization: 'marketing',
			analytics_storage: 'analytics',
			functionality_storage: 'functional',
			personalization_storage: 'preferences',
			security_storage: 'necessary'
		}
	},
	banner: {
		title: 'We value your privacy',
		message: 'Message',
		preferences_intro: 'Short intro.',
		accept_label: 'Accept All',
		reject_label: 'Reject All',
		manage_label: 'Manage Preferences',
		save_label: 'Save Preferences',
		reopen_label: 'Privacy Settings',
		show_reject: true,
		show_close: false,
		reopen_button: true,
		reopen_position: 'bottom-left',
		reopen_draggable: true,
		reopen_icon_url: '',
		position: 'bottom',
		layout: 'bar',
		animation: 'none'
	},
	cookies: {
		analytics: [
			{ name: '_ga', duration: '1 year 1 month 4 days', description: 'Google Analytics visitor cookie.' }
		]
	},
	services: {
		analytics: [ 'Google Analytics 4' ]
	},
	categories: {
		necessary: { label: 'Necessary', description: '', required: true },
		functional: { label: 'Functional', description: '', required: false },
		analytics: { label: 'Analytics', description: '', required: false },
		marketing: { label: 'Marketing', description: '', required: false },
		preferences: { label: 'Preferences', description: '', required: false }
	},
	i18n: {}
};

function clearStorage() {
	try {
		window.localStorage.clear();
	} catch ( e ) { /* ignore */ }
}

function clearCookies() {
	clearStorage();
	document.cookie.split( ';' ).forEach( function ( c ) {
		const name = c.split( '=' )[ 0 ].trim();
		if ( name ) {
			document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
		}
	} );
}

function boot( configOverrides, preSetup ) {
	jest.resetModules();
	document.body.innerHTML = '';
	window.PCMConfig = Object.assign( {}, JSON.parse( JSON.stringify( CONFIG ) ), configOverrides || {} );
	delete window.PrivacyConsent;
	delete window.PCMBlocker;
	delete window.PCMAnalytics;
	window.dataLayer = [];
	if ( typeof preSetup === 'function' ) {
		preSetup(); // e.g. insert blocked embeds before the scripts boot.
	}
	require( '../../consentguard/public/js/script-blocker.js' );
	require( '../../consentguard/public/js/analytics.js' );
	require( '../../consentguard/public/js/consent-manager.js' );
}

describe( 'PrivacyConsent state', () => {
	beforeEach( () => clearCookies() );

	test( 'no consent by default; banner is shown', () => {
		boot();
		expect( window.PrivacyConsent.getConsent() ).toBeNull();
		expect( window.PrivacyConsent.hasConsent( 'analytics' ) ).toBe( false );
		expect( window.PrivacyConsent.hasConsent( 'marketing' ) ).toBe( false );
		// Necessary is always granted even before a decision.
		expect( window.PrivacyConsent.hasConsent( 'necessary' ) ).toBe( true );
		expect( document.querySelector( '.pcm-banner.pcm-visible' ) ).not.toBeNull();
	} );

	test( 'rejectAll denies every optional category and keeps necessary', () => {
		boot();
		window.PrivacyConsent.rejectAll();
		const consent = window.PrivacyConsent.getConsent();
		expect( consent.necessary ).toBe( true );
		expect( consent.functional ).toBe( false );
		expect( consent.analytics ).toBe( false );
		expect( consent.marketing ).toBe( false );
		expect( consent.preferences ).toBe( false );
		expect( document.cookie ).toContain( 'pcm_consent=' );
	} );

	test( 'acceptAll grants every category', () => {
		boot();
		window.PrivacyConsent.acceptAll();
		const consent = window.PrivacyConsent.getConsent();
		Object.keys( consent ).forEach( ( slug ) => expect( consent[ slug ] ).toBe( true ) );
	} );

	test( 'stored consent is restored on next load', () => {
		boot();
		window.PrivacyConsent.acceptAll();
		boot(); // Simulates a new page view.
		expect( window.PrivacyConsent.hasConsent( 'analytics' ) ).toBe( true );
		expect( document.querySelector( '.pcm-banner.pcm-visible' ) ).toBeNull();
	} );

	test( 'consent version change re-prompts the visitor', () => {
		boot();
		window.PrivacyConsent.acceptAll();
		boot( { consentVersion: '2.0' } );
		expect( window.PrivacyConsent.getConsent() ).toBeNull();
		expect( document.querySelector( '.pcm-banner.pcm-visible' ) ).not.toBeNull();
	} );

	test( 'onChange listeners fire with the new state', () => {
		boot();
		const seen = [];
		window.PrivacyConsent.onChange( ( consent ) => seen.push( consent ) );
		window.PrivacyConsent.acceptAll();
		expect( seen ).toHaveLength( 1 );
		expect( seen[ 0 ].analytics ).toBe( true );
	} );

	test( 'privacy_consent_changed and granted/denied events dispatch', () => {
		boot();
		const events = [];
		[ 'privacy_consent_changed', 'privacy_analytics_granted', 'privacy_marketing_denied' ].forEach( ( name ) => {
			document.addEventListener( name, () => events.push( name ) );
		} );
		const chosen = {};
		window.PrivacyConsent.onChange( () => {} );
		// Custom save: analytics yes, marketing no.
		window.PrivacyConsent.openPreferences();
		document.querySelector( '[data-pcm-toggle="analytics"]' ).checked = true;
		document.querySelectorAll( '.pcm-modal .pcm-btn-primary' )[ 0 ].click();
		expect( events ).toContain( 'privacy_consent_changed' );
		expect( events ).toContain( 'privacy_analytics_granted' );
		expect( events ).toContain( 'privacy_marketing_denied' );
	} );
} );

describe( 'script unblocking', () => {
	beforeEach( () => clearCookies() );

	test( 'blocked analytics script executes only after analytics consent', () => {
		boot();
		const blocked = document.createElement( 'script' );
		blocked.setAttribute( 'type', 'text/plain' );
		blocked.setAttribute( 'data-pcm-managed', '1' );
		blocked.setAttribute( 'data-pcm-id', 'ga4' );
		blocked.setAttribute( 'data-pcm-category', 'analytics' );
		blocked.text = 'window.__ga4Ran = true;';
		document.body.appendChild( blocked );

		window.PrivacyConsent.rejectAll();
		expect( document.querySelector( '[data-pcm-activated]' ) ).toBeNull();

		window.PrivacyConsent.openPreferences();
		document.querySelector( '[data-pcm-toggle="analytics"]' ).checked = true;
		document.querySelectorAll( '.pcm-modal .pcm-btn-primary' )[ 0 ].click();

		const activated = document.querySelector( 'script[data-pcm-activated]' );
		expect( activated ).not.toBeNull();
		expect( activated.text ).toContain( '__ga4Ran' );
	} );

	test( 'external blocked script gets its src and attributes restored', () => {
		boot();
		const blocked = document.createElement( 'script' );
		blocked.setAttribute( 'type', 'text/plain' );
		blocked.setAttribute( 'data-pcm-id', 'cloudflare' );
		blocked.setAttribute( 'data-pcm-category', 'analytics' );
		blocked.setAttribute( 'data-pcm-src', 'https://static.cloudflareinsights.com/beacon.min.js' );
		blocked.setAttribute( 'data-pcm-attr-defer', '' );
		blocked.setAttribute( 'data-pcm-attr-cf-beacon', '{"token":"abc"}' );
		document.body.appendChild( blocked );

		window.PrivacyConsent.acceptAll();

		const activated = document.querySelector( 'script[data-pcm-activated]' );
		expect( activated ).not.toBeNull();
		expect( activated.src ).toBe( 'https://static.cloudflareinsights.com/beacon.min.js' );
		expect( activated.hasAttribute( 'defer' ) ).toBe( true );
		expect( activated.getAttribute( 'cf-beacon' ) ).toBe( '{"token":"abc"}' );
	} );

	test( 'a blocked script never executes twice', () => {
		boot();
		const blocked = document.createElement( 'script' );
		blocked.setAttribute( 'type', 'text/plain' );
		blocked.setAttribute( 'data-pcm-category', 'analytics' );
		blocked.text = 'window.__pcmRunCount = ( window.__pcmRunCount || 0 ) + 1;';
		document.body.appendChild( blocked );

		window.__pcmRunCount = 0;
		window.PrivacyConsent.acceptAll();
		window.PCMBlocker.unblock( { analytics: true } );
		expect( document.querySelectorAll( 'script[data-pcm-activated]' ) ).toHaveLength( 1 );
		expect( window.__pcmRunCount ).toBe( 1 );
	} );
} );

describe( 'Google Consent Mode updates', () => {
	beforeEach( () => clearCookies() );

	function lastConsentUpdate() {
		const updates = window.dataLayer.filter( ( entry ) => entry[ 0 ] === 'consent' && entry[ 1 ] === 'update' );
		return updates.length ? updates[ updates.length - 1 ][ 2 ] : null;
	}

	test( 'rejectAll sends denied for every non-necessary signal', () => {
		boot();
		window.PrivacyConsent.rejectAll();
		const update = lastConsentUpdate();
		expect( update.analytics_storage ).toBe( 'denied' );
		expect( update.ad_storage ).toBe( 'denied' );
		expect( update.ad_user_data ).toBe( 'denied' );
		expect( update.ad_personalization ).toBe( 'denied' );
		expect( update.security_storage ).toBe( 'granted' );
	} );

	test( 'acceptAll sends granted signals', () => {
		boot();
		window.PrivacyConsent.acceptAll();
		const update = lastConsentUpdate();
		expect( update.analytics_storage ).toBe( 'granted' );
		expect( update.ad_storage ).toBe( 'granted' );
	} );

	test( 'denied consent is never converted to granted', () => {
		boot();
		window.PrivacyConsent.openPreferences();
		document.querySelector( '[data-pcm-toggle="analytics"]' ).checked = true;
		// marketing stays unchecked.
		document.querySelectorAll( '.pcm-modal .pcm-btn-primary' )[ 0 ].click();
		const update = lastConsentUpdate();
		expect( update.analytics_storage ).toBe( 'granted' );
		expect( update.ad_storage ).toBe( 'denied' );
	} );
} );

describe( 'Global Privacy Control', () => {
	beforeEach( () => clearCookies() );

	function setGpc( value ) {
		Object.defineProperty( window.navigator, 'globalPrivacyControl', {
			value: value,
			configurable: true
		} );
	}

	afterEach( () => setGpc( undefined ) );

	test( 'GPC keeps marketing denied on Accept All', () => {
		setGpc( true );
		boot();
		window.PrivacyConsent.acceptAll();
		const consent = window.PrivacyConsent.getConsent();
		expect( consent.marketing ).toBe( false );
		expect( consent.analytics ).toBe( true );
		expect( window.PrivacyConsent.gpcDetected() ).toBe( true );
	} );

	test( 'GPC is ignored when the admin disabled it', () => {
		setGpc( true );
		boot( { respectGpc: false } );
		window.PrivacyConsent.acceptAll();
		expect( window.PrivacyConsent.getConsent().marketing ).toBe( true );
	} );

	test( 'an explicit marketing toggle still wins over GPC', () => {
		setGpc( true );
		boot();
		window.PrivacyConsent.openPreferences();
		document.querySelector( '[data-pcm-toggle="marketing"]' ).checked = true;
		document.querySelectorAll( '.pcm-modal .pcm-btn-primary' )[ 0 ].click();
		expect( window.PrivacyConsent.getConsent().marketing ).toBe( true );
	} );
} );

describe( 'jurisdiction consent modes', () => {
	beforeEach( () => clearCookies() );

	test( 'opt-out profile implies consent, still shows the banner, records nothing', () => {
		boot( { profile: { key: 'us_optout', requireConsent: false, showRejectAll: true, granular: true, mode: 'opt_out' } } );
		expect( window.PrivacyConsent.hasConsent( 'analytics' ) ).toBe( true );
		expect( window.PrivacyConsent.isImplied() ).toBe( true );
		expect( document.querySelector( '.pcm-banner.pcm-visible' ) ).not.toBeNull();
		expect( document.cookie ).not.toContain( 'pcm_consent=' );
	} );

	test( 'explicit rejection in an opt-out region overrides implied consent', () => {
		// Fake timers: revoking a running category schedules a page reload,
		// which jsdom cannot perform — the timer simply never fires here.
		jest.useFakeTimers();
		boot( { profile: { key: 'us_optout', requireConsent: false, showRejectAll: true, granular: true, mode: 'opt_out' } } );
		window.PrivacyConsent.rejectAll();
		expect( window.PrivacyConsent.hasConsent( 'analytics' ) ).toBe( false );
		expect( window.PrivacyConsent.isImplied() ).toBe( false );
		expect( document.cookie ).toContain( 'pcm_consent=' );
		jest.useRealTimers();
	} );

	test( 'notice-only profile hides the Reject All button on the banner', () => {
		boot( { profile: { key: 'notice', requireConsent: false, showRejectAll: true, granular: true, mode: 'notice_only' } } );
		const labels = Array.prototype.map.call(
			document.querySelectorAll( '.pcm-banner .pcm-btn' ),
			( b ) => b.textContent
		);
		expect( labels ).toContain( 'Accept All' );
		expect( labels ).not.toContain( 'Reject All' );
	} );
} );

describe( 'WP Consent API bridge', () => {
	beforeEach( () => clearCookies() );

	afterEach( () => {
		delete window.wp_set_consent;
	} );

	test( 'consent decisions are mirrored via wp_set_consent', () => {
		window.wp_set_consent = jest.fn();
		boot();
		window.PrivacyConsent.acceptAll();
		expect( window.wp_set_consent ).toHaveBeenCalledWith( 'statistics', 'allow' );
		expect( window.wp_set_consent ).toHaveBeenCalledWith( 'marketing', 'allow' );
		window.PrivacyConsent.rejectAll();
		expect( window.wp_set_consent ).toHaveBeenCalledWith( 'statistics', 'deny' );
	} );
} );

describe( 'preferences modal detail view', () => {
	beforeEach( () => clearCookies() );

	test( 'accordions render cookie details and toggle open state', () => {
		boot();
		window.PrivacyConsent.openPreferences();

		const analytics = document.querySelector( '#pcm-acc-analytics' );
		expect( analytics ).not.toBeNull();

		// Cookie table content comes from the configured inventory.
		const values = Array.prototype.map.call(
			analytics.querySelectorAll( '.pcm-cookie-val' ),
			( n ) => n.textContent
		);
		expect( values ).toContain( '_ga' );
		expect( values ).toContain( '1 year 1 month 4 days' );
		expect( analytics.textContent ).toContain( 'Google Analytics 4' );

		// First category opens by default; others expand on click.
		expect( document.querySelector( '#pcm-acc-necessary' ).classList.contains( 'pcm-open' ) ).toBe( true );
		const btn = analytics.querySelector( '.pcm-accordion-btn' );
		expect( btn.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
		btn.click();
		expect( analytics.classList.contains( 'pcm-open' ) ).toBe( true );
		expect( btn.getAttribute( 'aria-expanded' ) ).toBe( 'true' );
	} );

	test( 'long intro collapses behind Show more', () => {
		boot( { banner: Object.assign( {}, CONFIG.banner, {
			preferences_intro: 'A very long introduction text. '.repeat( 12 )
		} ) } );
		window.PrivacyConsent.openPreferences();

		const text = document.querySelector( '.pcm-intro-text' );
		const more = document.querySelector( '.pcm-show-more' );
		expect( text.classList.contains( 'pcm-clamped' ) ).toBe( true );
		expect( more.textContent ).toBe( 'Show more' );
		more.click();
		expect( text.classList.contains( 'pcm-clamped' ) ).toBe( false );
		expect( more.textContent ).toBe( 'Show less' );
	} );
} );

describe( 'floating revisit widget', () => {
	beforeEach( () => clearCookies() );

	test( 'honors the admin default position and custom icon', () => {
		boot( { banner: Object.assign( {}, CONFIG.banner, {
			reopen_position: 'bottom-right',
			reopen_icon_url: 'https://example.test/logo.png'
		} ) } );
		window.PrivacyConsent.acceptAll();

		const widget = document.querySelector( '.pcm-reopen' );
		expect( widget.classList.contains( 'pcm-reopen-bottom-right' ) ).toBe( true );
		expect( widget.querySelector( 'img.pcm-reopen-img' ).getAttribute( 'src' ) ).toBe( 'https://example.test/logo.png' );
		expect( widget.querySelector( 'button' ).getAttribute( 'aria-haspopup' ) ).toBe( 'dialog' );
	} );

	test( 'dragging moves the widget and persists the position', () => {
		boot();
		window.PrivacyConsent.acceptAll();
		const widget = document.querySelector( '.pcm-reopen' );

		// jsdom reports the widget rect at 0,0: grabbing at (100,100) and
		// moving to (200,300) drags it by (100,200).
		widget.dispatchEvent( new window.MouseEvent( 'pointerdown', { clientX: 100, clientY: 100, bubbles: true } ) );
		widget.dispatchEvent( new window.MouseEvent( 'pointermove', { clientX: 200, clientY: 300, bubbles: true } ) );
		widget.dispatchEvent( new window.MouseEvent( 'pointerup', { bubbles: true } ) );

		expect( widget.style.left ).toBe( '100px' );
		expect( widget.style.top ).toBe( '200px' );
		const saved = JSON.parse( window.localStorage.getItem( 'pcmReopenPos' ) );
		expect( saved ).toEqual( { x: 100, y: 200 } );
	} );

	test( 'a plain click (with pointer jitter) opens the preferences modal', () => {
		boot();
		window.PrivacyConsent.acceptAll();
		const widget = document.querySelector( '.pcm-reopen' );
		const btn = widget.querySelector( 'button' );

		// Real clicks often include tiny pointermove events — must stay a click.
		widget.dispatchEvent( new window.MouseEvent( 'pointerdown', { clientX: 30, clientY: 700, bubbles: true } ) );
		widget.dispatchEvent( new window.MouseEvent( 'pointermove', { clientX: 32, clientY: 701, bubbles: true } ) );
		widget.dispatchEvent( new window.MouseEvent( 'pointerup', { bubbles: true } ) );
		btn.click();

		expect( document.querySelector( '.pcm-modal' ) ).not.toBeNull();
		// Jitter below the threshold must not move or persist position.
		expect( window.localStorage.getItem( 'pcmReopenPos' ) ).toBeNull();
	} );

	test( 'clicking still works after a completed drag', ( done ) => {
		boot();
		window.PrivacyConsent.acceptAll();
		const widget = document.querySelector( '.pcm-reopen' );
		const btn = widget.querySelector( 'button' );

		// Drag...
		widget.dispatchEvent( new window.MouseEvent( 'pointerdown', { clientX: 10, clientY: 10, bubbles: true } ) );
		widget.dispatchEvent( new window.MouseEvent( 'pointermove', { clientX: 150, clientY: 150, bubbles: true } ) );
		widget.dispatchEvent( new window.MouseEvent( 'pointerup', { bubbles: true } ) );

		// ...the swallow guard clears on the next tick, then clicks work again.
		setTimeout( () => {
			btn.click();
			expect( document.querySelector( '.pcm-modal' ) ).not.toBeNull();
			done();
		}, 10 );
	} );

	test( 'a saved position is restored on the next page view', () => {
		window.localStorage.setItem( 'pcmReopenPos', JSON.stringify( { x: 60, y: 70 } ) );
		boot();
		window.PrivacyConsent.acceptAll();
		const widget = document.querySelector( '.pcm-reopen' );
		expect( widget.style.left ).toBe( '60px' );
		expect( widget.style.top ).toBe( '70px' );
	} );
} );

describe( 'embed placeholders', () => {
	beforeEach( () => clearCookies() );

	function addBlockedEmbed() {
		const frame = document.createElement( 'iframe' );
		frame.setAttribute( 'data-pcm-src', 'https://www.youtube.com/embed/abc123' );
		frame.setAttribute( 'data-pcm-category', 'functional' );
		frame.setAttribute( 'data-pcm-blocked-embed', '1' );
		document.body.appendChild( frame );
		return frame;
	}

	test( 'blocked embeds get a placeholder card naming the category', () => {
		let frame;
		boot( null, () => {
			frame = addBlockedEmbed();
		} );
		const card = document.querySelector( '.pcm-embed-placeholder' );
		expect( card ).not.toBeNull();
		expect( card.textContent ).toContain( 'Functional' );
		expect( card.textContent ).toContain( 'www.youtube.com' );
		expect( frame.src ).toBe( '' );
	} );

	test( 'Accept & load grants only that category and restores the iframe', () => {
		let frame;
		boot( null, () => {
			frame = addBlockedEmbed();
		} );
		document.querySelector( '.pcm-embed-accept' ).click();

		const consent = window.PrivacyConsent.getConsent();
		expect( consent.functional ).toBe( true );
		expect( consent.analytics ).toBe( false );
		expect( consent.marketing ).toBe( false );
		expect( frame.src ).toBe( 'https://www.youtube.com/embed/abc123' );
		expect( document.querySelector( '.pcm-embed-placeholder' ) ).toBeNull();
	} );

	test( 'embeds load immediately when the category is already granted', () => {
		boot();
		window.PrivacyConsent.acceptAll();
		// Simulate the next page view with stored consent + a blocked embed.
		let frame;
		boot( null, () => {
			frame = addBlockedEmbed();
		} );
		expect( frame.src ).toBe( 'https://www.youtube.com/embed/abc123' );
		expect( document.querySelector( '.pcm-embed-placeholder' ) ).toBeNull();
	} );

	test( 'grantCategory preserves previously granted categories', () => {
		boot();
		window.PrivacyConsent.openPreferences();
		document.querySelector( '[data-pcm-toggle="analytics"]' ).checked = true;
		document.querySelectorAll( '.pcm-modal .pcm-btn-primary' )[ 0 ].click();

		window.PrivacyConsent.grantCategory( 'functional' );
		const consent = window.PrivacyConsent.getConsent();
		expect( consent.analytics ).toBe( true );
		expect( consent.functional ).toBe( true );
		expect( consent.marketing ).toBe( false );
	} );
} );

describe( 'accessibility', () => {
	beforeEach( () => clearCookies() );

	test( 'banner announces itself via a polite live region', ( done ) => {
		boot();
		setTimeout( () => {
			const live = document.getElementById( 'pcm-live-region' );
			expect( live ).not.toBeNull();
			expect( live.getAttribute( 'aria-live' ) ).toBe( 'polite' );
			expect( live.textContent ).toBe( 'We value your privacy' );
			done();
		}, 150 );
	} );

	test( 'modal has dialog semantics and toggles are labelled', () => {
		boot();
		window.PrivacyConsent.openPreferences();
		const modal = document.querySelector( '.pcm-modal' );
		expect( modal.getAttribute( 'role' ) ).toBe( 'dialog' );
		expect( modal.getAttribute( 'aria-modal' ) ).toBe( 'true' );
		const toggle = document.querySelector( '[data-pcm-toggle="analytics"]' );
		expect( toggle.getAttribute( 'aria-labelledby' ) ).toBe( 'pcm-cat-analytics' );
	} );

	test( 'Escape closes the preferences modal', () => {
		boot();
		window.PrivacyConsent.openPreferences();
		const overlay = document.querySelector( '.pcm-overlay' );
		overlay.dispatchEvent( new window.KeyboardEvent( 'keydown', { key: 'Escape', bubbles: true } ) );
		expect( document.querySelector( '.pcm-overlay' ) ).toBeNull();
	} );
} );
