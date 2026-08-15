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
		accept_label: 'Accept All',
		reject_label: 'Reject All',
		manage_label: 'Manage Preferences',
		save_label: 'Save Preferences',
		reopen_label: 'Privacy Settings',
		show_reject: true,
		show_close: false,
		reopen_button: true,
		position: 'bottom',
		layout: 'bar',
		animation: 'none'
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

function clearCookies() {
	document.cookie.split( ';' ).forEach( function ( c ) {
		const name = c.split( '=' )[ 0 ].trim();
		if ( name ) {
			document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
		}
	} );
}

function boot( configOverrides ) {
	jest.resetModules();
	document.body.innerHTML = '';
	window.PCMConfig = Object.assign( {}, JSON.parse( JSON.stringify( CONFIG ) ), configOverrides || {} );
	delete window.PrivacyConsent;
	delete window.PCMBlocker;
	delete window.PCMAnalytics;
	window.dataLayer = [];
	require( '../../public/js/script-blocker.js' );
	require( '../../public/js/analytics.js' );
	require( '../../public/js/consent-manager.js' );
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

describe( 'accessibility', () => {
	beforeEach( () => clearCookies() );

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
