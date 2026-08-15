<?php
/**
 * Duplicate tracking scanner.
 *
 * @package PCM
 */

namespace PCM;

defined( 'ABSPATH' ) || exit;

/**
 * Fetches the site's own homepage over a loopback request and counts how
 * many independent copies of each tracker are present. Combined with the
 * plugin registry this attributes duplicates to their likely source.
 *
 * Runs only on demand ("Scan Now") or via an explicit REST call — never on
 * normal page loads.
 */
class Duplicate_Tracking_Detector {

	const RESULTS_OPTION = 'pcm_last_scan';

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
	 * Tracker signatures. Each entry counts DISTINCT initialization points.
	 *
	 * @return array<string, array{label: string, patterns: string[]}>
	 */
	public function signatures() {
		return array(
			'ga4'        => array(
				'label'    => __( 'Google Analytics 4', 'privacypress' ),
				'patterns' => array(
					'#googletagmanager\.com/gtag/js\?id=(G-[A-Z0-9]+)#i',
					'#gtag\(\s*[\'"]config[\'"]\s*,\s*[\'"](G-[A-Z0-9]+)[\'"]#i',
				),
			),
			'gtm'        => array(
				'label'    => __( 'Google Tag Manager', 'privacypress' ),
				'patterns' => array(
					'#googletagmanager\.com/gtm\.js\?id=(GTM-[A-Z0-9]+)#i',
					'#[\'"](GTM-[A-Z0-9]+)[\'"]#',
				),
			),
			'clarity'    => array(
				'label'    => __( 'Microsoft Clarity', 'privacypress' ),
				'patterns' => array(
					'#clarity\.ms/tag/([a-z0-9]+)#i',
					'#"clarity"\s*,\s*"script"\s*,\s*"([a-z0-9]+)"#i',
				),
			),
			'cloudflare' => array(
				'label'    => __( 'Cloudflare Web Analytics', 'privacypress' ),
				'patterns' => array(
					'#static\.cloudflareinsights\.com/beacon(?:\.min)?\.js#i',
				),
			),
			'meta_pixel' => array(
				'label'    => __( 'Meta Pixel', 'privacypress' ),
				'patterns' => array(
					'#connect\.facebook\.net/[a-z_]+/fbevents\.js#i',
					'#fbq\(\s*[\'"]init[\'"]\s*,\s*[\'"](\d+)[\'"]#',
				),
			),
		);
	}

	/**
	 * Runs the scan and stores the results.
	 *
	 * @return array|\WP_Error Scan result.
	 */
	public function scan() {
		$html = $this->fetch_homepage();
		if ( is_wp_error( $html ) ) {
			return $html;
		}

		$active_plugins = $this->detector->detect_active();
		$results        = array();

		foreach ( $this->signatures() as $service => $meta ) {
			$instances = $this->count_instances( $html, $meta['patterns'] );
			$sources   = $this->attribute_sources( $service, $active_plugins );

			$results[ $service ] = array(
				'label'     => $meta['label'],
				'instances' => $instances,
				'sources'   => $sources,
				'duplicate' => $instances > 1 || count( $sources ) > 1,
			);
		}

		$scan = array(
			'time'    => time(),
			'results' => $results,
			'scripts' => $this->collect_third_party_scripts( $html ),
		);
		update_option( self::RESULTS_OPTION, $scan, false );

		/**
		 * Fires after a duplicate-tracking scan completed.
		 *
		 * @param array $scan Scan results.
		 */
		do_action( 'pcm_scan_completed', $scan );

		return $scan;
	}

	/**
	 * Returns the stored last scan.
	 *
	 * @return array
	 */
	public function last_scan() {
		$scan = get_option( self::RESULTS_OPTION, array() );
		return is_array( $scan ) ? $scan : array();
	}

	/**
	 * Count of services flagged as duplicates in the last scan.
	 *
	 * @return int
	 */
	public function duplicate_count() {
		$scan  = $this->last_scan();
		$count = 0;
		foreach ( (array) ( $scan['results'] ?? array() ) as $result ) {
			if ( ! empty( $result['duplicate'] ) ) {
				$count++;
			}
		}
		return $count;
	}

	/**
	 * Fetches the homepage HTML via loopback.
	 *
	 * @return string|\WP_Error
	 */
	private function fetch_homepage() {
		$url = add_query_arg( 'pcm_scan', '1', home_url( '/' ) );

		$response = wp_remote_get(
			$url,
			array(
				'timeout'   => 15,
				'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
				'headers'   => array( 'Cache-Control' => 'no-cache' ),
				// Identify ourselves; some caches vary on UA.
				'user-agent' => 'PCM-Scanner/' . PCM_VERSION . '; ' . home_url(),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'pcm_scan_failed',
				sprintf(
					/* translators: %s: error message */
					__( 'Could not fetch the homepage for scanning: %s', 'privacypress' ),
					$response->get_error_message()
				)
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 400 ) {
			return new \WP_Error(
				'pcm_scan_http_error',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'Homepage scan returned HTTP status %d.', 'privacypress' ),
					$code
				)
			);
		}

		return (string) wp_remote_retrieve_body( $response );
	}

	/**
	 * Counts distinct tracker instances. Consent-blocked copies rendered by
	 * this plugin (type="text/plain" with data-pcm markers) count as ONE
	 * managed instance; unmanaged live copies each count separately.
	 *
	 * @param string   $html     Page HTML.
	 * @param string[] $patterns Regexes.
	 * @return int
	 */
	private function count_instances( $html, array $patterns ) {
		$ids   = array();
		$plain = 0;

		foreach ( $patterns as $pattern ) {
			if ( ! preg_match_all( $pattern, $html, $matches, PREG_OFFSET_CAPTURE ) ) {
				continue;
			}
			foreach ( $matches[0] as $i => $match ) {
				$id = isset( $matches[1][ $i ][0] ) && '' !== $matches[1][ $i ][0]
					? $matches[1][ $i ][0]
					: 'instance-' . $match[1];

				// Determine whether this occurrence sits inside a PCM-managed template.
				$context = substr( $html, max( 0, $match[1] - 400 ), 400 );
				if ( false !== strpos( $context, 'data-pcm-' ) ) {
					$plain = 1; // All managed copies collapse to one logical instance.
				} else {
					$ids[ $id ] = true;
				}
			}
		}
		return count( $ids ) + $plain;
	}

	/**
	 * Lists likely sources for a service.
	 *
	 * @param string $service        Service id.
	 * @param array  $active_plugins Active known plugins.
	 * @return string[] Source labels.
	 */
	private function attribute_sources( $service, array $active_plugins ) {
		$sources = array();

		$ours = array(
			'ga4'        => pcm_get_setting( 'ga4.enabled', false ) && '' !== pcm_get_setting( 'ga4.measurement_id', '' ),
			'gtm'        => pcm_get_setting( 'gtm.enabled', false ) && '' !== pcm_get_setting( 'gtm.container_id', '' ),
			'clarity'    => pcm_get_setting( 'clarity.enabled', false ) && '' !== pcm_get_setting( 'clarity.project_id', '' ),
			'cloudflare' => pcm_get_setting( 'cloudflare.enabled', false ) && '' !== pcm_get_setting( 'cloudflare.token', '' ),
			'meta_pixel' => false,
		);
		if ( ! empty( $ours[ $service ] ) ) {
			$sources[] = __( 'PrivacyPress', 'privacypress' );
		}

		foreach ( $active_plugins as $plugin ) {
			if ( ! in_array( $service, $plugin['services'], true ) ) {
				continue;
			}
			if ( 'site-kit' === $plugin['slug'] && ! $this->detector->site_kit_analytics_active() ) {
				continue;
			}
			$sources[] = $plugin['name'];
		}
		return $sources;
	}

	/**
	 * Collects third-party script hosts for the cookie/script scanner and
	 * classifies them using the blocker lists + admin classifications.
	 *
	 * @param string $html Page HTML.
	 * @return array[] {host, category, known}.
	 */
	private function collect_third_party_scripts( $html ) {
		if ( ! preg_match_all( '#<script\b[^>]*\bsrc\s*=\s*["\']?([^"\'\s>]+)#i', $html, $matches ) ) {
			return array();
		}

		$site_host       = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$domains         = (array) pcm_get_setting( 'blocker.domains', array() );
		$classifications = (array) pcm_get_setting( 'scanner.classifications', array() );

		$hosts = array();
		foreach ( $matches[1] as $src ) {
			$host = strtolower( (string) wp_parse_url( $src, PHP_URL_HOST ) );
			if ( '' === $host || $host === $site_host || isset( $hosts[ $host ] ) ) {
				continue;
			}

			$category = 'unknown';
			$known    = false;
			foreach ( $domains as $domain => $cat ) {
				if ( $host === strtolower( $domain ) || str_ends_with( $host, '.' . ltrim( strtolower( $domain ), '*.' ) ) ) {
					$category = $cat;
					$known    = true;
					break;
				}
			}
			if ( ! $known && isset( $classifications[ $host ] ) ) {
				$category = $classifications[ $host ];
				$known    = true;
			}

			$hosts[ $host ] = array(
				'host'     => $host,
				'category' => $category,
				'known'    => $known,
			);
		}
		return array_values( $hosts );
	}
}
