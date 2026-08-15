<?php
/**
 * Settings repository: defaults, access and sanitization.
 *
 * @package PCM
 */

namespace PCM;

defined( 'ABSPATH' ) || exit;

/**
 * Single source of truth for plugin configuration.
 * Stored as one autoload-disabled option: pcm_settings.
 */
class Settings {

	const OPTION = 'pcm_settings';

	/**
	 * Singleton instance.
	 *
	 * @var Settings|null
	 */
	private static $instance = null;

	/**
	 * Cached merged settings.
	 *
	 * @var array|null
	 */
	private $cache = null;

	/**
	 * Returns the shared instance.
	 *
	 * @return Settings
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Default configuration. Strings run through translation at access time
	 * in the UI; stored defaults remain translatable keys where possible.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'consent'       => array(
				'banner_enabled'    => true,
				'cookie_expiry'     => 180,   // Days the consent cookie persists.
				'retention_days'    => 365,   // Server-side record retention.
				'store_records'     => true,
				'consent_version'   => '1.0',
				'reprompt_on_change' => true, // New consent version re-prompts visitors.
			),
			'categories'    => self::default_categories(),
			'banner'        => array(
				'position'          => 'bottom',      // bottom | top | bottom-left | bottom-right | center.
				'layout'            => 'bar',          // bar | box.
				'title'             => __( 'We value your privacy', 'privacy-consent-manager' ),
				'message'           => __( 'We use cookies and similar technologies to improve your experience, understand website usage and support our services.', 'privacy-consent-manager' ),
				'accept_label'      => __( 'Accept All', 'privacy-consent-manager' ),
				'reject_label'      => __( 'Reject All', 'privacy-consent-manager' ),
				'manage_label'      => __( 'Manage Preferences', 'privacy-consent-manager' ),
				'save_label'        => __( 'Save Preferences', 'privacy-consent-manager' ),
				'reopen_label'      => __( 'Privacy Settings', 'privacy-consent-manager' ),
				'show_close'        => false,
				'show_reject'       => true,
				'reopen_button'     => true,
				'logo_url'          => '',
				'primary_color'     => '#1a73e8',
				'text_color'        => '#1f2937',
				'background_color'  => '#ffffff',
				'button_text_color' => '#ffffff',
				'font_size'         => 15,
				'border_radius'     => 12,
				'theme'             => 'light', // light | dark | auto.
				'animation'         => 'slide', // slide | fade | none.
				'hide_in_admin'     => true,
				'hide_in_elementor' => true,
			),
			'ga4'           => array(
				'enabled'        => false,
				'measurement_id' => '',
				'category'       => 'analytics',
				'anonymize_ip'   => true,
			),
			'consent_mode'  => array(
				'enabled'            => true,
				'ads_data_redaction' => true,
				'url_passthrough'    => false,
				'wait_for_update'    => 500,
			),
			'clarity'       => array(
				'enabled'    => false,
				'project_id' => '',
				'category'   => 'analytics',
			),
			'cloudflare'    => array(
				'enabled'         => false,
				'token'           => '',
				'category'        => 'analytics',
				'require_consent' => true,
			),
			'gtm'           => array(
				'enabled'      => false,
				'container_id' => '',
				'category'     => 'marketing',
			),
			'custom_scripts' => array(),
			'blocker'       => array(
				'enabled'   => true,
				'domains'   => self::default_blocked_domains(),
				'allowlist' => array(),
			),
			'jurisdictions' => array(
				'geo_enabled'     => false,
				'default_profile' => 'gdpr',
				'rules'           => array(
					'IN' => 'dpdp',
					'GB' => 'uk_gdpr',
				),
				'profiles'        => self::default_profiles(),
			),
			'dpdp'          => array(
				'notice_text'     => '',
				'purpose_text'    => '',
				'rights_text'     => '',
				'contact_email'   => '',
				'grievance_info'  => '',
			),
			'policies'      => array(
				'privacy_page_id' => 0,
				'cookie_page_id'  => 0,
				'policy_version'  => '1.0',
				'generated_cookie_policy' => '',
			),
			'scanner'       => array(
				'classifications' => array(), // domain => category, admin-defined for unknowns.
				'last_scan'       => array(),
			),
			'advanced'      => array(
				'debug'               => false,
				'delete_on_uninstall' => false,
				'respect_gpc'         => true,
			),
		);
	}

	/**
	 * Built-in consent categories.
	 *
	 * @return array
	 */
	public static function default_categories() {
		return array(
			'necessary'   => array(
				'label'       => __( 'Necessary', 'privacy-consent-manager' ),
				'description' => __( 'Required for the website to function. Cannot be disabled.', 'privacy-consent-manager' ),
				'required'    => true,
				'builtin'     => true,
			),
			'functional'  => array(
				'label'       => __( 'Functional', 'privacy-consent-manager' ),
				'description' => __( 'Enables enhanced functionality such as embedded media and live chat.', 'privacy-consent-manager' ),
				'required'    => false,
				'builtin'     => true,
			),
			'analytics'   => array(
				'label'       => __( 'Analytics', 'privacy-consent-manager' ),
				'description' => __( 'Helps us understand how visitors use the website (e.g. Google Analytics, Microsoft Clarity).', 'privacy-consent-manager' ),
				'required'    => false,
				'builtin'     => true,
			),
			'marketing'   => array(
				'label'       => __( 'Marketing', 'privacy-consent-manager' ),
				'description' => __( 'Used for advertising, remarketing and measuring ad performance.', 'privacy-consent-manager' ),
				'required'    => false,
				'builtin'     => true,
			),
			'preferences' => array(
				'label'       => __( 'Preferences', 'privacy-consent-manager' ),
				'description' => __( 'Remembers optional personalization choices such as language or region.', 'privacy-consent-manager' ),
				'required'    => false,
				'builtin'     => true,
			),
		);
	}

	/**
	 * Known tracking domains and their default categories.
	 *
	 * @return array<string,string> domain => category.
	 */
	public static function default_blocked_domains() {
		return array(
			'google-analytics.com'          => 'analytics',
			'www.google-analytics.com'      => 'analytics',
			'googletagmanager.com'          => 'analytics',
			'www.googletagmanager.com'      => 'analytics',
			'clarity.ms'                    => 'analytics',
			'www.clarity.ms'                => 'analytics',
			'static.cloudflareinsights.com' => 'analytics',
			'connect.facebook.net'          => 'marketing',
			'snap.licdn.com'                => 'marketing',
			'static.hotjar.com'             => 'analytics',
			'cdn.mouseflow.com'             => 'analytics',
			'googleads.g.doubleclick.net'   => 'marketing',
			'www.googleadservices.com'      => 'marketing',
		);
	}

	/**
	 * Default jurisdiction consent profiles.
	 *
	 * @return array
	 */
	public static function default_profiles() {
		return array(
			'gdpr'      => array(
				'label'           => __( 'EU / EEA (GDPR)', 'privacy-consent-manager' ),
				'require_consent' => true,
				'show_reject_all' => true,
				'granular'        => true,
				'mode'            => 'opt_in',
			),
			'uk_gdpr'   => array(
				'label'           => __( 'United Kingdom (UK GDPR)', 'privacy-consent-manager' ),
				'require_consent' => true,
				'show_reject_all' => true,
				'granular'        => true,
				'mode'            => 'opt_in',
			),
			'dpdp'      => array(
				'label'           => __( 'India (DPDP Act)', 'privacy-consent-manager' ),
				'require_consent' => true,
				'show_reject_all' => true,
				'granular'        => true,
				'mode'            => 'opt_in',
			),
			'us_optout' => array(
				'label'           => __( 'US-style (opt-out)', 'privacy-consent-manager' ),
				'require_consent' => false,
				'show_reject_all' => true,
				'granular'        => true,
				'mode'            => 'opt_out',
			),
			'default'   => array(
				'label'           => __( 'Default (rest of world)', 'privacy-consent-manager' ),
				'require_consent' => true,
				'show_reject_all' => true,
				'granular'        => true,
				'mode'            => 'opt_in',
			),
		);
	}

	/**
	 * Returns the full merged settings array.
	 *
	 * @return array
	 */
	public function all() {
		if ( null === $this->cache ) {
			$saved       = get_option( self::OPTION, array() );
			$this->cache = self::merge_recursive( self::defaults(), is_array( $saved ) ? $saved : array() );
		}
		return $this->cache;
	}

	/**
	 * Gets a value via dot notation.
	 *
	 * @param string $key     Dot key, e.g. 'ga4.measurement_id'.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		$value = $this->all();
		foreach ( explode( '.', $key ) as $segment ) {
			if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
				return $default;
			}
			$value = $value[ $segment ];
		}
		return $value;
	}

	/**
	 * Updates a settings section after sanitization.
	 *
	 * @param string $section Section key (e.g. 'ga4').
	 * @param array  $values  New values for the section.
	 * @return bool
	 */
	public function update_section( $section, array $values ) {
		$all             = $this->all();
		$all[ $section ] = self::merge_recursive(
			isset( $all[ $section ] ) && is_array( $all[ $section ] ) ? $all[ $section ] : array(),
			$values
		);

		// Replace-style keys where merging would resurrect deleted entries.
		$replace = array( 'custom_scripts', 'categories' );
		if ( in_array( $section, $replace, true ) ) {
			$all[ $section ] = $values;
		}
		if ( 'blocker' === $section && isset( $values['domains'] ) ) {
			$all['blocker']['domains'] = $values['domains'];
		}
		if ( 'blocker' === $section && isset( $values['allowlist'] ) ) {
			$all['blocker']['allowlist'] = $values['allowlist'];
		}
		if ( 'jurisdictions' === $section && isset( $values['rules'] ) ) {
			$all['jurisdictions']['rules'] = $values['rules'];
		}

		$this->cache = $all;
		return update_option( self::OPTION, $all, false );
	}

	/**
	 * Clears the internal cache (used by tests).
	 */
	public function flush_cache() {
		$this->cache = null;
	}

	/**
	 * Recursively merges saved values over defaults. Saved scalars win;
	 * saved arrays merge into default arrays; list-style arrays replace.
	 *
	 * @param array $defaults Defaults.
	 * @param array $saved    Saved values.
	 * @return array
	 */
	public static function merge_recursive( array $defaults, array $saved ) {
		foreach ( $saved as $key => $value ) {
			if ( is_array( $value ) && isset( $defaults[ $key ] ) && is_array( $defaults[ $key ] )
				&& ! wp_is_numeric_array( $value ) && ! wp_is_numeric_array( $defaults[ $key ] ) ) {
				$defaults[ $key ] = self::merge_recursive( $defaults[ $key ], $value );
			} else {
				$defaults[ $key ] = $value;
			}
		}
		return $defaults;
	}

	/**
	 * Sanitizes a whole settings payload keyed by section. Unknown sections
	 * are dropped. Used by the admin UI and the REST settings endpoint.
	 *
	 * Presence-based: only keys that exist in the input are emitted, so a
	 * form that carries just part of a section never resets the rest of it
	 * (update_section() merges the result over the stored values). Admin
	 * checkboxes therefore ship a hidden empty-value companion so that
	 * "unchecked" still posts the key.
	 *
	 * @param array $input Raw input.
	 * @return array Sanitized sections only.
	 */
	public static function sanitize( array $input ) {
		$out = array();

		$pick_bools = static function ( array $src, array $keys ) {
			$picked = array();
			foreach ( $keys as $key ) {
				if ( array_key_exists( $key, $src ) ) {
					$picked[ $key ] = ! empty( $src[ $key ] );
				}
			}
			return $picked;
		};

		if ( isset( $input['consent'] ) ) {
			$c              = (array) $input['consent'];
			$out['consent'] = $pick_bools( $c, array( 'banner_enabled', 'store_records', 'reprompt_on_change' ) );
			if ( array_key_exists( 'cookie_expiry', $c ) ) {
				$out['consent']['cookie_expiry'] = max( 1, min( 730, absint( $c['cookie_expiry'] ) ) );
			}
			if ( array_key_exists( 'retention_days', $c ) ) {
				$out['consent']['retention_days'] = max( 30, min( 3650, absint( $c['retention_days'] ) ) );
			}
			if ( array_key_exists( 'consent_version', $c ) ) {
				$out['consent']['consent_version'] = sanitize_text_field( $c['consent_version'] );
			}
		}

		if ( isset( $input['categories'] ) && is_array( $input['categories'] ) ) {
			$cats = array();
			foreach ( $input['categories'] as $slug => $cat ) {
				$slug = pcm_sanitize_category_slug( $slug );
				if ( '' === $slug ) {
					continue;
				}
				$builtin       = in_array( $slug, pcm_builtin_categories(), true );
				$cats[ $slug ] = array(
					'label'       => sanitize_text_field( $cat['label'] ?? $slug ),
					'description' => sanitize_textarea_field( $cat['description'] ?? '' ),
					'required'    => 'necessary' === $slug ? true : ! empty( $cat['required'] ),
					'builtin'     => $builtin,
				);
			}
			// Necessary can never be removed.
			if ( ! isset( $cats['necessary'] ) ) {
				$cats = array( 'necessary' => self::default_categories()['necessary'] ) + $cats;
			}
			$out['categories'] = $cats;
		}

		if ( isset( $input['banner'] ) ) {
			$b             = (array) $input['banner'];
			$out['banner'] = $pick_bools( $b, array( 'show_close', 'show_reject', 'reopen_button', 'hide_in_admin', 'hide_in_elementor' ) );

			$enums = array(
				'position'  => array( array( 'bottom', 'top', 'bottom-left', 'bottom-right', 'center' ), 'bottom' ),
				'layout'    => array( array( 'bar', 'box' ), 'bar' ),
				'animation' => array( array( 'slide', 'fade', 'none' ), 'slide' ),
				'theme'     => array( array( 'light', 'dark', 'auto' ), 'light' ),
			);
			foreach ( $enums as $key => $enum ) {
				if ( array_key_exists( $key, $b ) ) {
					$out['banner'][ $key ] = in_array( $b[ $key ], $enum[0], true ) ? $b[ $key ] : $enum[1];
				}
			}

			foreach ( array( 'title', 'accept_label', 'reject_label', 'manage_label', 'save_label', 'reopen_label' ) as $key ) {
				if ( array_key_exists( $key, $b ) ) {
					$out['banner'][ $key ] = sanitize_text_field( $b[ $key ] );
				}
			}
			if ( array_key_exists( 'message', $b ) ) {
				$out['banner']['message'] = sanitize_textarea_field( $b['message'] );
			}
			if ( array_key_exists( 'logo_url', $b ) ) {
				$out['banner']['logo_url'] = esc_url_raw( $b['logo_url'] );
			}

			$colors = array(
				'primary_color'     => '#1a73e8',
				'text_color'        => '#1f2937',
				'background_color'  => '#ffffff',
				'button_text_color' => '#ffffff',
			);
			foreach ( $colors as $key => $fallback ) {
				if ( array_key_exists( $key, $b ) ) {
					$out['banner'][ $key ] = sanitize_hex_color( $b[ $key ] ) ?: $fallback;
				}
			}
			if ( array_key_exists( 'font_size', $b ) ) {
				$out['banner']['font_size'] = max( 10, min( 24, absint( $b['font_size'] ) ) );
			}
			if ( array_key_exists( 'border_radius', $b ) ) {
				$out['banner']['border_radius'] = max( 0, min( 40, absint( $b['border_radius'] ) ) );
			}
		}

		if ( isset( $input['ga4'] ) ) {
			$g          = (array) $input['ga4'];
			$out['ga4'] = $pick_bools( $g, array( 'enabled', 'anonymize_ip' ) );
			if ( array_key_exists( 'measurement_id', $g ) ) {
				$out['ga4']['measurement_id'] = pcm_sanitize_ga4_id( $g['measurement_id'] );
			}
			if ( array_key_exists( 'category', $g ) ) {
				$out['ga4']['category'] = pcm_sanitize_category_slug( $g['category'] ) ?: 'analytics';
			}
		}

		if ( isset( $input['consent_mode'] ) ) {
			$m                   = (array) $input['consent_mode'];
			$out['consent_mode'] = $pick_bools( $m, array( 'enabled', 'ads_data_redaction', 'url_passthrough' ) );
			if ( array_key_exists( 'wait_for_update', $m ) ) {
				$out['consent_mode']['wait_for_update'] = max( 0, min( 5000, absint( $m['wait_for_update'] ) ) );
			}
		}

		if ( isset( $input['clarity'] ) ) {
			$cl             = (array) $input['clarity'];
			$out['clarity'] = $pick_bools( $cl, array( 'enabled' ) );
			if ( array_key_exists( 'project_id', $cl ) ) {
				$out['clarity']['project_id'] = pcm_sanitize_clarity_id( $cl['project_id'] );
			}
			if ( array_key_exists( 'category', $cl ) ) {
				$out['clarity']['category'] = pcm_sanitize_category_slug( $cl['category'] ) ?: 'analytics';
			}
		}

		if ( isset( $input['cloudflare'] ) ) {
			$cf                = (array) $input['cloudflare'];
			$out['cloudflare'] = $pick_bools( $cf, array( 'enabled', 'require_consent' ) );
			if ( array_key_exists( 'token', $cf ) ) {
				$out['cloudflare']['token'] = pcm_sanitize_cf_token( $cf['token'] );
			}
			if ( array_key_exists( 'category', $cf ) ) {
				$out['cloudflare']['category'] = pcm_sanitize_category_slug( $cf['category'] ) ?: 'analytics';
			}
		}

		if ( isset( $input['gtm'] ) ) {
			$t          = (array) $input['gtm'];
			$out['gtm'] = $pick_bools( $t, array( 'enabled' ) );
			if ( array_key_exists( 'container_id', $t ) ) {
				$out['gtm']['container_id'] = pcm_sanitize_gtm_id( $t['container_id'] );
			}
			if ( array_key_exists( 'category', $t ) ) {
				$out['gtm']['category'] = pcm_sanitize_category_slug( $t['category'] ) ?: 'marketing';
			}
		}

		if ( isset( $input['custom_scripts'] ) && is_array( $input['custom_scripts'] ) ) {
			$scripts = array();
			foreach ( $input['custom_scripts'] as $script ) {
				$script = (array) $script;
				$code   = pcm_sanitize_script_code( $script['code'] ?? '' );
				$name   = sanitize_text_field( $script['name'] ?? '' );
				if ( '' === $code || '' === $name ) {
					continue;
				}
				$scripts[] = array(
					'id'       => sanitize_key( $script['id'] ?? '' ) ?: sanitize_key( wp_generate_uuid4() ),
					'name'     => $name,
					'category' => pcm_sanitize_category_slug( $script['category'] ?? 'marketing' ) ?: 'marketing',
					'position' => in_array( $script['position'] ?? '', array( 'header', 'body', 'footer' ), true ) ? $script['position'] : 'footer',
					'enabled'  => ! empty( $script['enabled'] ),
					'code'     => $code,
				);
			}
			$out['custom_scripts'] = $scripts;
		}

		if ( isset( $input['blocker'] ) ) {
			$bl             = (array) $input['blocker'];
			$out['blocker'] = $pick_bools( $bl, array( 'enabled' ) );
			if ( array_key_exists( 'domains', $bl ) ) {
				$domains = array();
				foreach ( (array) $bl['domains'] as $domain => $category ) {
					// Plain lists ("domain" without category) default to analytics.
					$is_list = is_int( $domain );
					$domain  = pcm_sanitize_domain( $is_list ? $category : $domain );
					if ( '' === $domain ) {
						continue;
					}
					$domains[ $domain ] = $is_list ? 'analytics' : ( pcm_sanitize_category_slug( $category ) ?: 'analytics' );
				}
				$out['blocker']['domains'] = $domains;
			}
			if ( array_key_exists( 'allowlist', $bl ) ) {
				$allow = array();
				foreach ( (array) $bl['allowlist'] as $domain ) {
					$domain = pcm_sanitize_domain( $domain );
					if ( '' !== $domain ) {
						$allow[] = $domain;
					}
				}
				$out['blocker']['allowlist'] = array_values( array_unique( $allow ) );
			}
		}

		if ( isset( $input['jurisdictions'] ) ) {
			$j                    = (array) $input['jurisdictions'];
			$out['jurisdictions'] = $pick_bools( $j, array( 'geo_enabled' ) );
			if ( array_key_exists( 'default_profile', $j ) ) {
				$out['jurisdictions']['default_profile'] = sanitize_key( $j['default_profile'] ) ?: 'default';
			}
			if ( array_key_exists( 'rules', $j ) ) {
				$rules = array();
				foreach ( (array) $j['rules'] as $country => $profile ) {
					$country = strtoupper( sanitize_key( $country ) );
					if ( preg_match( '/^[A-Z]{2}$/', $country ) ) {
						$rules[ $country ] = sanitize_key( $profile );
					}
				}
				$out['jurisdictions']['rules'] = $rules;
			}
			if ( array_key_exists( 'profiles', $j ) ) {
				$profiles = array();
				foreach ( (array) $j['profiles'] as $key => $profile ) {
					$key = sanitize_key( $key );
					if ( '' === $key ) {
						continue;
					}
					$profiles[ $key ] = array(
						'label'           => sanitize_text_field( $profile['label'] ?? $key ),
						'require_consent' => ! empty( $profile['require_consent'] ),
						'show_reject_all' => ! empty( $profile['show_reject_all'] ),
						'granular'        => ! empty( $profile['granular'] ),
						'mode'            => in_array( $profile['mode'] ?? '', array( 'opt_in', 'opt_out', 'notice_only' ), true ) ? $profile['mode'] : 'opt_in',
					);
				}
				$out['jurisdictions']['profiles'] = $profiles ?: self::default_profiles();
			}
		}

		if ( isset( $input['dpdp'] ) ) {
			$d           = (array) $input['dpdp'];
			$out['dpdp'] = array();
			foreach ( array( 'notice_text', 'purpose_text', 'rights_text', 'grievance_info' ) as $key ) {
				if ( array_key_exists( $key, $d ) ) {
					$out['dpdp'][ $key ] = sanitize_textarea_field( $d[ $key ] );
				}
			}
			if ( array_key_exists( 'contact_email', $d ) ) {
				$out['dpdp']['contact_email'] = sanitize_email( $d['contact_email'] );
			}
		}

		if ( isset( $input['policies'] ) ) {
			$p               = (array) $input['policies'];
			$out['policies'] = array();
			if ( array_key_exists( 'privacy_page_id', $p ) ) {
				$out['policies']['privacy_page_id'] = absint( $p['privacy_page_id'] );
			}
			if ( array_key_exists( 'cookie_page_id', $p ) ) {
				$out['policies']['cookie_page_id'] = absint( $p['cookie_page_id'] );
			}
			if ( array_key_exists( 'policy_version', $p ) ) {
				$out['policies']['policy_version'] = sanitize_text_field( $p['policy_version'] );
			}
			if ( array_key_exists( 'generated_cookie_policy', $p ) ) {
				$out['policies']['generated_cookie_policy'] = wp_kses_post( $p['generated_cookie_policy'] );
			}
		}

		if ( isset( $input['scanner'] ) ) {
			$s               = (array) $input['scanner'];
			$classifications = array();
			foreach ( (array) ( $s['classifications'] ?? array() ) as $domain => $category ) {
				$domain = pcm_sanitize_domain( $domain );
				if ( '' !== $domain ) {
					$classifications[ $domain ] = pcm_sanitize_category_slug( $category ) ?: 'unknown';
				}
			}
			$out['scanner'] = array( 'classifications' => $classifications );
		}

		if ( isset( $input['advanced'] ) ) {
			$out['advanced'] = $pick_bools( (array) $input['advanced'], array( 'debug', 'delete_on_uninstall', 'respect_gpc' ) );
		}

		return $out;
	}
}
