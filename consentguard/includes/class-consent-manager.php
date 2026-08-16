<?php
/**
 * Consent domain logic.
 *
 * @package PCM
 */

namespace PCM;

defined( 'ABSPATH' ) || exit;

/**
 * Owns the consent model: category evaluation, record sanitization,
 * and the server-visible consent cookie (for logged-in/uncached cases).
 *
 * The authoritative consent state on cached pages lives client-side in the
 * first-party cookie read by public/js/consent-manager.js; this class mirrors
 * the same rules server-side.
 */
class Consent_Manager {

	const COOKIE = 'pcm_consent';

	/**
	 * Storage backend.
	 *
	 * @var Consent_Storage
	 */
	private $storage;

	/**
	 * Constructor.
	 *
	 * @param Consent_Storage $storage Storage.
	 */
	public function __construct( Consent_Storage $storage ) {
		$this->storage = $storage;
	}

	/**
	 * Registers hooks.
	 */
	public function register() {
		add_action( 'pcm_consent_recorded', array( $this, 'fire_change_action' ), 10, 1 );
	}

	/**
	 * Returns all configured categories.
	 *
	 * @return array
	 */
	public function get_categories() {
		$categories = pcm_get_setting( 'categories', Settings::default_categories() );

		/**
		 * Filters the configured consent categories.
		 *
		 * @param array $categories slug => {label, description, required, builtin}.
		 */
		return apply_filters( 'pcm_consent_categories', $categories );
	}

	/**
	 * Parses the consent cookie for the current request, if present.
	 *
	 * NOTE: on cached pages this only works for uncached requests (admin,
	 * AJAX, REST). Frontend consent decisions are made in JavaScript.
	 *
	 * @return array|null Decoded state or null when absent/invalid.
	 */
	public function get_request_consent() {
		if ( empty( $_COOKIE[ self::COOKIE ] ) ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON validated below.
		$raw  = wp_unslash( $_COOKIE[ self::COOKIE ] );
		$data = json_decode( rawurldecode( $raw ), true );
		// An empty categories array is a valid all-denied decision;
		// only a missing/invalid structure means "no consent given yet".
		if ( ! is_array( $data ) || ! isset( $data['categories'] ) || ! is_array( $data['categories'] ) ) {
			return null;
		}

		// Stale consent versions are treated as "no consent" when re-prompt is on.
		if ( pcm_get_setting( 'consent.reprompt_on_change', true )
			&& ( $data['version'] ?? '' ) !== pcm_get_setting( 'consent.consent_version', '1.0' ) ) {
			return null;
		}

		$state = array();
		foreach ( $this->get_categories() as $slug => $category ) {
			$state[ $slug ] = ! empty( $category['required'] ) || ! empty( $data['categories'][ $slug ] );
		}
		return $state;
	}

	/**
	 * Whether the current request has consent for a category.
	 * Absent consent means denied for every non-required category.
	 *
	 * @param string $category Category slug.
	 * @return bool
	 */
	public function has_consent( $category ) {
		$categories = $this->get_categories();
		if ( isset( $categories[ $category ]['required'] ) && $categories[ $category ]['required'] ) {
			return true;
		}
		$state = $this->get_request_consent();
		return is_array( $state ) && ! empty( $state[ $category ] );
	}

	/**
	 * Validates and normalizes an inbound consent payload (REST).
	 *
	 * @param array $payload Raw payload.
	 * @return array|\WP_Error Normalized DB record.
	 */
	public function sanitize_record( array $payload ) {
		$categories = isset( $payload['categories'] ) && is_array( $payload['categories'] ) ? $payload['categories'] : null;
		if ( null === $categories ) {
			return new \WP_Error( 'pcm_invalid_payload', __( 'Missing consent categories.', 'consentguard' ), array( 'status' => 400 ) );
		}

		$anonymous_id = isset( $payload['anonymous_id'] ) ? (string) $payload['anonymous_id'] : '';
		if ( '' !== $anonymous_id && ! wp_is_uuid( $anonymous_id ) ) {
			return new \WP_Error( 'pcm_invalid_id', __( 'Invalid anonymous identifier.', 'consentguard' ), array( 'status' => 400 ) );
		}

		$known  = $this->get_categories();
		$record = array(
			'consent_id'      => pcm_generate_uuid(),
			'anonymous_id'    => $anonymous_id,
			'consent_version' => sanitize_text_field( (string) ( $payload['consent_version'] ?? '' ) ),
			'policy_version'  => sanitize_text_field( (string) ( $payload['policy_version'] ?? '' ) ),
			'necessary'       => 1,
			'functional'      => 0,
			'analytics'       => 0,
			'marketing'       => 0,
			'preferences'     => 0,
			'region'          => strtoupper( substr( sanitize_key( (string) ( $payload['region'] ?? '' ) ), 0, 8 ) ),
			'language'        => substr( sanitize_text_field( (string) ( $payload['language'] ?? '' ) ), 0, 12 ),
			'action'          => in_array( $payload['action'] ?? '', array( 'accept_all', 'reject_all', 'custom', 'withdraw', 'update' ), true ) ? $payload['action'] : 'update',
			'created_at'      => current_time( 'mysql', true ),
		);

		$builtin = pcm_builtin_categories();
		$extra   = array();
		foreach ( $categories as $slug => $granted ) {
			$slug = pcm_sanitize_category_slug( $slug );
			if ( '' === $slug || ! isset( $known[ $slug ] ) ) {
				continue; // Unknown categories are dropped, never stored.
			}
			$granted = ! empty( $known[ $slug ]['required'] ) ? true : (bool) $granted;
			if ( in_array( $slug, $builtin, true ) ) {
				$record[ $slug ] = $granted ? 1 : 0;
			} else {
				$extra[ $slug ] = $granted;
			}
		}
		$record['necessary']        = 1;
		$record['extra_categories'] = $extra ? wp_json_encode( $extra ) : null;

		return $record;
	}

	/**
	 * Persists a consent record and announces the change.
	 *
	 * @param array $payload Raw payload from the REST endpoint.
	 * @return array|\WP_Error {consent_id} on success.
	 */
	public function record_consent( array $payload ) {
		$record = $this->sanitize_record( $payload );
		if ( is_wp_error( $record ) ) {
			return $record;
		}

		$this->storage->insert( $record );
		delete_transient( 'pcm_consent_stats' );

		/**
		 * Fires after a consent record was accepted.
		 *
		 * @param array $record Normalized record (no PII).
		 */
		do_action( 'pcm_consent_recorded', $record );

		return array( 'consent_id' => $record['consent_id'] );
	}

	/**
	 * Bridges the storage event to the documented public action.
	 *
	 * @param array $record Consent record.
	 */
	public function fire_change_action( $record ) {
		$consent = array(
			'necessary'   => true,
			'functional'  => ! empty( $record['functional'] ),
			'analytics'   => ! empty( $record['analytics'] ),
			'marketing'   => ! empty( $record['marketing'] ),
			'preferences' => ! empty( $record['preferences'] ),
		);
		if ( ! empty( $record['extra_categories'] ) ) {
			$extra = json_decode( (string) $record['extra_categories'], true );
			if ( is_array( $extra ) ) {
				$consent = array_merge( $consent, array_map( 'boolval', $extra ) );
			}
		}

		/**
		 * Fires whenever a visitor's consent changes.
		 *
		 * @param array $consent slug => bool granted map.
		 */
		do_action( 'pcm_consent_changed', $consent );
	}

	/**
	 * Client configuration consumed by consent-manager.js.
	 *
	 * @return array
	 */
	public function get_client_config() {
		$categories = array();
		foreach ( $this->get_categories() as $slug => $category ) {
			$categories[ $slug ] = array(
				'label'       => $category['label'],
				'description' => $category['description'],
				'required'    => ! empty( $category['required'] ),
			);
		}

		$banner   = pcm_get_setting( 'banner', array() );
		$policies = pcm_get_setting( 'policies', array() );

		// Per-locale text overrides (Settings → Translations). The current
		// locale reflects multilingual plugins (WPML/Polylang) per request.
		$overrides = pcm_get_setting( 'translations.' . get_locale(), array() );
		if ( ! empty( $overrides['banner'] ) && is_array( $overrides['banner'] ) ) {
			$banner = array_merge( $banner, $overrides['banner'] );
		}
		if ( ! empty( $overrides['categories'] ) && is_array( $overrides['categories'] ) ) {
			foreach ( $overrides['categories'] as $slug => $texts ) {
				if ( isset( $categories[ $slug ] ) ) {
					$categories[ $slug ] = array_merge( $categories[ $slug ], array_intersect_key( (array) $texts, array( 'label' => 1, 'description' => 1 ) ) );
				}
			}
		}

		// Cookie discovery runs only for administrators browsing the site.
		$can_discover = current_user_can( 'manage_options' );
		$known        = array( 'pcm_consent', 'wordpress_*', 'wp-*', 'wp_*', 'PHPSESSID', 'comment_*', '_ga*', '_gid', '_gat*', '_clck', '_clsk', '_fbp', '_gcl_*', 'CLID', 'MUID' );
		foreach ( (array) pcm_get_setting( 'cookies', array() ) as $rows ) {
			foreach ( (array) $rows as $row ) {
				if ( ! empty( $row['name'] ) ) {
					$known[] = $row['name'];
				}
			}
		}

		// Cookie inventory + managed services per category (modal detail tables).
		$cookies = array();
		foreach ( (array) pcm_get_setting( 'cookies', array() ) as $slug => $rows ) {
			if ( isset( $categories[ $slug ] ) ) {
				$cookies[ $slug ] = array_values( (array) $rows );
			}
		}

		$services = array();
		$managed  = array(
			'ga4'        => __( 'Google Analytics 4', 'consentguard' ),
			'clarity'    => __( 'Microsoft Clarity', 'consentguard' ),
			'cloudflare' => __( 'Cloudflare Web Analytics', 'consentguard' ),
			'gtm'        => __( 'Google Tag Manager', 'consentguard' ),
		);
		foreach ( $managed as $key => $label ) {
			if ( pcm_get_setting( $key . '.enabled' ) ) {
				$services[ pcm_get_setting( $key . '.category', 'analytics' ) ][] = $label;
			}
		}
		foreach ( (array) pcm_get_setting( 'custom_scripts', array() ) as $script ) {
			if ( ! empty( $script['enabled'] ) ) {
				$services[ $script['category'] ][] = $script['name'];
			}
		}

		return array(
			'cookieName'     => self::COOKIE,
			'cookieExpiry'   => (int) pcm_get_setting( 'consent.cookie_expiry', 180 ),
			'consentVersion' => (string) pcm_get_setting( 'consent.consent_version', '1.0' ),
			'policyVersion'  => (string) ( $policies['policy_version'] ?? '1.0' ),
			'repromptOnChange' => (bool) pcm_get_setting( 'consent.reprompt_on_change', true ),
			'categories'     => $categories,
			'cookies'        => $cookies,
			'services'       => $services,
			'banner'         => $banner,
			'privacyUrl'     => ! empty( $policies['privacy_page_id'] ) ? get_permalink( (int) $policies['privacy_page_id'] ) : '',
			'cookieUrl'      => ! empty( $policies['cookie_page_id'] ) ? get_permalink( (int) $policies['cookie_page_id'] ) : '',
			'restUrl'        => esc_url_raw( rest_url( 'pcm/v1/consent' ) ),
			'storeRecords'   => (bool) pcm_get_setting( 'consent.store_records', true ),
			'respectGpc'     => (bool) pcm_get_setting( 'advanced.respect_gpc', true ),
			'debug'          => (bool) pcm_get_setting( 'advanced.debug', false ),
			'discover'       => $can_discover,
			'discoverUrl'    => $can_discover ? esc_url_raw( rest_url( 'pcm/v1/discovered' ) ) : '',
			'restNonce'      => $can_discover ? wp_create_nonce( 'wp_rest' ) : '',
			'knownCookies'   => array_values( array_unique( $known ) ),
			'language'       => get_locale(),
			'i18n'           => array(
				'preferencesTitle' => __( 'Customise Consent Preferences', 'consentguard' ),
				'alwaysActive'     => __( 'Always Active', 'consentguard' ),
				'privacyPolicy'    => __( 'Privacy Policy', 'consentguard' ),
				'cookiePolicy'     => __( 'Cookie Policy', 'consentguard' ),
				'close'            => __( 'Close', 'consentguard' ),
				'consentId'        => __( 'Consent ID', 'consentguard' ),
				'showMore'         => __( 'Show more', 'consentguard' ),
				'showLess'         => __( 'Show less', 'consentguard' ),
				'cookie'           => __( 'Cookie', 'consentguard' ),
				'duration'         => __( 'Duration', 'consentguard' ),
				'description'      => __( 'Description', 'consentguard' ),
				'noCookies'        => __( 'No cookies to display for this category.', 'consentguard' ),
				'managedServices'  => __( 'Managed services', 'consentguard' ),
				'expandCategory'   => __( 'Show cookie details for', 'consentguard' ),
				/* translators: %s: consent category label */
				'embedBlocked'     => __( 'This embedded content is blocked until you accept "%s" cookies.', 'consentguard' ),
				'embedAccept'      => __( 'Accept & load', 'consentguard' ),
			),
		);
	}
}
