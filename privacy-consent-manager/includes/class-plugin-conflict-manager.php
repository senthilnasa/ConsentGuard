<?php
/**
 * Plugin Conflict Manager.
 *
 * @package PCM
 */

namespace PCM;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the list of potential tracking conflicts between this plugin's
 * managed integrations and other active plugins, and performs SAFE
 * mitigations only:
 *
 * - Never deactivates another plugin.
 * - Never edits another plugin's database settings.
 * - Uses only publicly supported filters (e.g. Site Kit's
 *   googlesitekit_{module}_tag_blocked) or standard WordPress dequeueing.
 * - Where no supported mechanism exists, reports that manual action is
 *   required instead of guessing.
 */
class Plugin_Conflict_Manager {

	const IGNORED_OPTION     = 'pcm_ignored_conflicts';
	const MITIGATIONS_OPTION = 'pcm_conflict_mitigations';

	/**
	 * Detector.
	 *
	 * @var Plugin_Detector
	 */
	private $detector;

	/**
	 * Constructor.
	 *
	 * @param Plugin_Detector $detector Detector.
	 */
	public function __construct( Plugin_Detector $detector ) {
		$this->detector = $detector;
	}

	/**
	 * Registers hooks.
	 */
	public function register() {}

	/**
	 * Human labels for managed services.
	 *
	 * @return array<string,string>
	 */
	public static function service_labels() {
		return array(
			'ga4'        => __( 'Google Analytics 4', 'privacy-consent-manager' ),
			'gtm'        => __( 'Google Tag Manager', 'privacy-consent-manager' ),
			'clarity'    => __( 'Microsoft Clarity', 'privacy-consent-manager' ),
			'cloudflare' => __( 'Cloudflare Web Analytics', 'privacy-consent-manager' ),
			'meta_pixel' => __( 'Meta Pixel', 'privacy-consent-manager' ),
		);
	}

	/**
	 * Computes current conflicts.
	 *
	 * A conflict exists when a service is configured in this plugin AND an
	 * active plugin is known to inject the same service.
	 *
	 * @return array[] Each: {id, plugin, plugin_name, service, service_label,
	 *                 mitigation ('supported'|'manual'), mitigated, ignored, note}.
	 */
	public function get_conflicts() {
		$ours = array(
			'ga4'        => (bool) pcm_get_setting( 'ga4.enabled', false ) && '' !== pcm_get_setting( 'ga4.measurement_id', '' ),
			'gtm'        => (bool) pcm_get_setting( 'gtm.enabled', false ) && '' !== pcm_get_setting( 'gtm.container_id', '' ),
			'clarity'    => (bool) pcm_get_setting( 'clarity.enabled', false ) && '' !== pcm_get_setting( 'clarity.project_id', '' ),
			'cloudflare' => (bool) pcm_get_setting( 'cloudflare.enabled', false ) && '' !== pcm_get_setting( 'cloudflare.token', '' ),
			'meta_pixel' => $this->has_custom_script_signature( 'connect.facebook.net' ),
		);

		$ignored    = (array) get_option( self::IGNORED_OPTION, array() );
		$mitigated  = (array) get_option( self::MITIGATIONS_OPTION, array() );
		$labels     = self::service_labels();
		$conflicts  = array();

		foreach ( $this->detector->detect_active() as $basename => $plugin ) {
			foreach ( $plugin['services'] as $service ) {
				if ( empty( $ours[ $service ] ) ) {
					continue;
				}

				// Site Kit: only a conflict if its Analytics module is on.
				if ( 'site-kit' === $plugin['slug'] && in_array( $service, array( 'ga4', 'gtm' ), true )
					&& ! $this->detector->site_kit_analytics_active() ) {
					continue;
				}

				$id          = $plugin['slug'] . ':' . $service;
				$supported   = $this->mitigation_supported( $plugin['slug'], $service );
				$conflicts[] = array(
					'id'            => $id,
					'plugin'        => $basename,
					'plugin_name'   => $plugin['name'],
					'service'       => $service,
					'service_label' => isset( $labels[ $service ] ) ? $labels[ $service ] : $service,
					'mitigation'    => $supported ? 'supported' : 'manual',
					'mitigated'     => in_array( $id, $mitigated, true ),
					'ignored'       => in_array( $id, $ignored, true ),
					'note'          => $supported
						? __( 'Duplicate output can be suppressed using this plugin\'s supported hooks.', 'privacy-consent-manager' )
						: __( 'Automatic conflict resolution is not available. Please disable this tracking feature in the other plugin\'s own settings.', 'privacy-consent-manager' ),
				);
			}
		}

		/**
		 * Filters the computed plugin conflicts.
		 *
		 * @param array[] $conflicts Conflict descriptors.
		 */
		return apply_filters( 'pcm_plugin_conflicts', $conflicts );
	}

	/**
	 * Conflicts that are neither ignored nor mitigated (for badges).
	 *
	 * @return array[]
	 */
	public function get_open_conflicts() {
		return array_values(
			array_filter(
				$this->get_conflicts(),
				static function ( $c ) {
					return empty( $c['ignored'] ) && empty( $c['mitigated'] );
				}
			)
		);
	}

	/**
	 * Whether a supported, safe mitigation exists for a plugin+service pair.
	 *
	 * @param string $slug    Plugin slug from the registry.
	 * @param string $service Service id.
	 * @return bool
	 */
	public function mitigation_supported( $slug, $service ) {
		$supported = array(
			// Site Kit documents googlesitekit_{module}_tag_blocked filters.
			'site-kit:ga4'             => true,
			'site-kit:gtm'             => true,
			// The official Clarity plugin prints via wp_head; we can detect
			// and suppress the duplicate client-side, but there is no
			// supported server-side switch — treat as manual.
			'microsoft-clarity:clarity' => false,
			'cloudflare:cloudflare'    => false,
		);
		$key = $slug . ':' . $service;
		$default = isset( $supported[ $key ] ) ? $supported[ $key ] : false;

		/**
		 * Filters whether a safe automatic mitigation exists for a conflict.
		 *
		 * @param bool   $default Supported.
		 * @param string $slug    Plugin slug.
		 * @param string $service Service id.
		 */
		return (bool) apply_filters( 'pcm_conflict_mitigation_supported', $default, $slug, $service );
	}

	/**
	 * Marks a conflict as mitigated. The actual suppression is performed by
	 * the integration shims (integrations/*.php) which read this option.
	 *
	 * @param string $conflict_id Conflict id (slug:service).
	 * @return true|\WP_Error
	 */
	public function apply_mitigation( $conflict_id ) {
		$conflict = $this->find( $conflict_id );
		if ( ! $conflict ) {
			return new \WP_Error( 'pcm_unknown_conflict', __( 'Unknown conflict.', 'privacy-consent-manager' ) );
		}
		if ( 'supported' !== $conflict['mitigation'] ) {
			return new \WP_Error(
				'pcm_manual_only',
				__( 'Automatic conflict resolution is not available for this plugin. Please disable the tracking feature manually in that plugin\'s settings.', 'privacy-consent-manager' )
			);
		}

		$mitigated = (array) get_option( self::MITIGATIONS_OPTION, array() );
		if ( ! in_array( $conflict_id, $mitigated, true ) ) {
			$mitigated[] = $conflict_id;
			update_option( self::MITIGATIONS_OPTION, array_values( $mitigated ), false );
		}

		/**
		 * Fires after a conflict mitigation was enabled.
		 *
		 * @param array $conflict Conflict descriptor.
		 */
		do_action( 'pcm_conflict_mitigated', $conflict );

		return true;
	}

	/**
	 * Removes a mitigation.
	 *
	 * @param string $conflict_id Conflict id.
	 */
	public function remove_mitigation( $conflict_id ) {
		$mitigated = (array) get_option( self::MITIGATIONS_OPTION, array() );
		update_option( self::MITIGATIONS_OPTION, array_values( array_diff( $mitigated, array( $conflict_id ) ) ), false );
	}

	/**
	 * Ignores / un-ignores a conflict.
	 *
	 * @param string $conflict_id Conflict id.
	 * @param bool   $ignore      Ignore state.
	 */
	public function set_ignored( $conflict_id, $ignore ) {
		$ignored = (array) get_option( self::IGNORED_OPTION, array() );
		if ( $ignore && ! in_array( $conflict_id, $ignored, true ) ) {
			$ignored[] = $conflict_id;
		} elseif ( ! $ignore ) {
			$ignored = array_diff( $ignored, array( $conflict_id ) );
		}
		update_option( self::IGNORED_OPTION, array_values( $ignored ), false );
	}

	/**
	 * Whether a mitigation is currently enabled (read by integration shims).
	 *
	 * @param string $conflict_id Conflict id.
	 * @return bool
	 */
	public static function is_mitigation_enabled( $conflict_id ) {
		return in_array( $conflict_id, (array) get_option( self::MITIGATIONS_OPTION, array() ), true );
	}

	/**
	 * Finds a conflict by id.
	 *
	 * @param string $conflict_id Conflict id.
	 * @return array|null
	 */
	private function find( $conflict_id ) {
		foreach ( $this->get_conflicts() as $conflict ) {
			if ( $conflict['id'] === $conflict_id ) {
				return $conflict;
			}
		}
		return null;
	}

	/**
	 * Whether any enabled custom script contains a signature.
	 *
	 * @param string $needle Signature substring.
	 * @return bool
	 */
	private function has_custom_script_signature( $needle ) {
		foreach ( (array) pcm_get_setting( 'custom_scripts', array() ) as $script ) {
			if ( ! empty( $script['enabled'] ) && false !== stripos( (string) ( $script['code'] ?? '' ), $needle ) ) {
				return true;
			}
		}
		return false;
	}
}
