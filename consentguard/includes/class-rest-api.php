<?php
/**
 * REST API endpoints.
 *
 * @package PCM
 */

namespace PCM;

defined( 'ABSPATH' ) || exit;

/**
 * Namespace pcm/v1.
 *
 * POST /consent          public   — record a consent decision (no PII accepted).
 * GET  /settings         admin    — read settings (secrets included; manage_options).
 * POST /settings         admin    — update settings sections.
 * POST /scan             admin    — run the duplicate-tracking scan.
 * GET  /records          admin    — paginated consent records.
 * POST /conflicts/(id)   admin    — mitigate/ignore a conflict.
 *
 * The public consent endpoint intentionally does not require a nonce:
 * consent is submitted from fully cached pages where WordPress nonces are
 * unavailable/stale. It accepts only a fixed, validated shape, stores no
 * PII, and is rate-limited per IP hash (the hash is never persisted).
 */
class Rest_Api {

	/**
	 * Storage.
	 *
	 * @var Consent_Storage
	 */
	private $storage;

	/**
	 * Scanner.
	 *
	 * @var Duplicate_Tracking_Detector
	 */
	private $scanner;

	/**
	 * Constructor.
	 *
	 * @param Consent_Storage             $storage Storage.
	 * @param Duplicate_Tracking_Detector $scanner Scanner.
	 */
	public function __construct( Consent_Storage $storage, Duplicate_Tracking_Detector $scanner ) {
		$this->storage = $storage;
		$this->scanner = $scanner;
	}

	/**
	 * Registers hooks.
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers routes.
	 */
	public function register_routes() {
		register_rest_route(
			'pcm/v1',
			'/consent',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'record_consent' ),
				'permission_callback' => array( $this, 'consent_rate_limit' ),
				'args'                => array(
					'categories'      => array(
						'required' => true,
						'type'     => 'object',
					),
					'anonymous_id'    => array( 'type' => 'string' ),
					'consent_version' => array( 'type' => 'string' ),
					'policy_version'  => array( 'type' => 'string' ),
					'region'          => array( 'type' => 'string' ),
					'language'        => array( 'type' => 'string' ),
					'action'          => array( 'type' => 'string' ),
				),
			)
		);

		register_rest_route(
			'pcm/v1',
			'/settings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'admin_permission' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'admin_permission' ),
				),
			)
		);

		register_rest_route(
			'pcm/v1',
			'/scan',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'run_scan' ),
				'permission_callback' => array( $this, 'admin_permission' ),
			)
		);

		register_rest_route(
			'pcm/v1',
			'/records',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_records' ),
				'permission_callback' => array( $this, 'admin_permission' ),
				'args'                => array(
					'page'     => array(
						'type'    => 'integer',
						'default' => 1,
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => 20,
					),
				),
			)
		);

		register_rest_route(
			'pcm/v1',
			'/discovered',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'record_discovered' ),
				'permission_callback' => array( $this, 'admin_permission' ),
			)
		);

		register_rest_route(
			'pcm/v1',
			'/conflicts/(?P<id>[a-z0-9:_-]+)',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_conflict' ),
				'permission_callback' => array( $this, 'admin_permission' ),
				'args'                => array(
					'operation' => array(
						'required' => true,
						'type'     => 'string',
						'enum'     => array( 'mitigate', 'unmitigate', 'ignore', 'unignore' ),
					),
				),
			)
		);
	}

	/**
	 * Admin capability + nonce check (cookie auth requires X-WP-Nonce).
	 *
	 * @return bool|\WP_Error
	 */
	public function admin_permission() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'pcm_forbidden',
				__( 'You are not allowed to do that.', 'consentguard' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
		return true;
	}

	/**
	 * Lightweight per-client rate limit for the public consent endpoint.
	 * Uses a transient keyed by a salted hash of the IP; the raw IP is never
	 * stored and the hash expires after one minute.
	 *
	 * @return bool|\WP_Error
	 */
	public function consent_rate_limit() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		if ( '' === $ip ) {
			return true;
		}
		$key   = 'pcm_rl_' . substr( hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) ), 0, 24 );
		$count = (int) get_transient( $key );
		if ( $count >= 20 ) {
			return new \WP_Error(
				'pcm_rate_limited',
				__( 'Too many consent submissions. Please try again shortly.', 'consentguard' ),
				array( 'status' => 429 )
			);
		}
		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * POST /consent.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function record_consent( \WP_REST_Request $request ) {
		$consent_manager = pcm()->module( 'consent' );

		$payload = array(
			'categories'      => (array) $request->get_param( 'categories' ),
			'anonymous_id'    => (string) $request->get_param( 'anonymous_id' ),
			'consent_version' => (string) $request->get_param( 'consent_version' ),
			'policy_version'  => (string) $request->get_param( 'policy_version' ),
			'region'          => (string) $request->get_param( 'region' ),
			'language'        => (string) $request->get_param( 'language' ),
			'action'          => (string) $request->get_param( 'action' ),
		);

		$result = $consent_manager->record_consent( $payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( $result );
	}

	/**
	 * POST /discovered — records unknown cookie/localStorage names spotted
	 * by an administrator's browser for later classification. Admin-only.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function record_discovered( \WP_REST_Request $request ) {
		$items      = (array) $request->get_param( 'items' );
		$discovered = (array) get_option( 'pcm_discovered_cookies', array() );
		$added      = 0;

		foreach ( array_slice( $items, 0, 50 ) as $item ) {
			$name = sanitize_text_field( (string) ( $item['name'] ?? '' ) );
			$type = in_array( $item['type'] ?? '', array( 'cookie', 'localStorage' ), true ) ? $item['type'] : 'cookie';
			if ( '' === $name || strlen( $name ) > 128 || isset( $discovered[ $name ] ) ) {
				continue;
			}
			$discovered[ $name ] = array(
				'type'       => $type,
				'first_seen' => current_time( 'mysql', true ),
			);
			++$added;
		}
		if ( $added > 0 ) {
			// Bounded: keep at most 200 pending discoveries.
			update_option( 'pcm_discovered_cookies', array_slice( $discovered, -200, null, true ), false );
		}
		return rest_ensure_response( array( 'added' => $added ) );
	}

	/**
	 * GET /settings.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_settings() {
		return rest_ensure_response( pcm_get_settings() );
	}

	/**
	 * POST /settings.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_settings( \WP_REST_Request $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) || empty( $body ) ) {
			return new \WP_Error( 'pcm_empty', __( 'No settings provided.', 'consentguard' ), array( 'status' => 400 ) );
		}

		$sanitized = Settings::sanitize( $body );
		$settings  = Settings::instance();
		foreach ( $sanitized as $section => $values ) {
			$settings->update_section( $section, $values );
		}

		return rest_ensure_response( $settings->all() );
	}

	/**
	 * POST /scan.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function run_scan() {
		$result = $this->scanner->scan();
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( $result );
	}

	/**
	 * GET /records — admin only. Records contain no PII by design, but they
	 * are still never exposed publicly.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_records( \WP_REST_Request $request ) {
		return rest_ensure_response(
			$this->storage->get_records( (int) $request->get_param( 'page' ), (int) $request->get_param( 'per_page' ) )
		);
	}

	/**
	 * POST /conflicts/{id}.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_conflict( \WP_REST_Request $request ) {
		$conflicts = pcm()->module( 'conflicts' );
		$id        = sanitize_text_field( (string) $request['id'] );

		switch ( $request->get_param( 'operation' ) ) {
			case 'mitigate':
				$result = $conflicts->apply_mitigation( $id );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				break;
			case 'unmitigate':
				$conflicts->remove_mitigation( $id );
				break;
			case 'ignore':
				$conflicts->set_ignored( $id, true );
				break;
			case 'unignore':
				$conflicts->set_ignored( $id, false );
				break;
		}

		return rest_ensure_response( array( 'conflicts' => $conflicts->get_conflicts() ) );
	}
}
