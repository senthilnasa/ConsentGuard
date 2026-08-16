<?php
/**
 * Admin UI: menu, form handling, assets.
 *
 * @package PCM
 */

namespace PCM\Admin;

use PCM\Duplicate_Tracking_Detector;
use PCM\Plugin_Conflict_Manager;
use PCM\Consent_Storage;
use PCM\Pdf_Writer;
use PCM\Policy_Manager;
use PCM\Security;
use PCM\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the "ConsentGuard" menu, renders view files and processes
 * settings form submissions (nonce + capability checked).
 */
class Admin {

	/**
	 * Page slugs => view files + titles.
	 *
	 * @var array<string, array{title: string, view: string}>
	 */
	private $pages = array();

	/**
	 * Conflict manager.
	 *
	 * @var Plugin_Conflict_Manager
	 */
	private $conflicts;

	/**
	 * Scanner.
	 *
	 * @var Duplicate_Tracking_Detector
	 */
	private $scanner;

	/**
	 * Storage.
	 *
	 * @var Consent_Storage
	 */
	private $storage;

	/**
	 * Constructor.
	 *
	 * @param Plugin_Conflict_Manager     $conflicts Conflict manager.
	 * @param Duplicate_Tracking_Detector $scanner   Scanner.
	 * @param Consent_Storage             $storage   Storage.
	 */
	public function __construct( Plugin_Conflict_Manager $conflicts, Duplicate_Tracking_Detector $scanner, Consent_Storage $storage ) {
		$this->conflicts = $conflicts;
		$this->scanner   = $scanner;
		$this->storage   = $storage;
	}

	/**
	 * Registers hooks.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_save' ) );
		add_action( 'admin_post_pcm_conflict', array( $this, 'handle_conflict_action' ) );
		add_action( 'admin_post_pcm_scan', array( $this, 'handle_scan' ) );
		add_action( 'admin_post_pcm_generate_policy', array( $this, 'handle_generate_policy' ) );
		add_action( 'admin_post_pcm_export_records', array( $this, 'handle_export_records' ) );
		add_action( 'admin_post_pcm_delete_record', array( $this, 'handle_delete_record' ) );
		add_action( 'admin_post_pcm_export_proof', array( $this, 'handle_export_proof' ) );
		add_action( 'admin_post_pcm_export_settings', array( $this, 'handle_export_settings' ) );
		add_action( 'admin_post_pcm_import_settings', array( $this, 'handle_import_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Menu structure.
	 */
	public function register_menu() {
		$this->pages = array(
			'pcm-dashboard'     => array(
				'title' => __( 'Dashboard', 'consentguard' ),
				'view'  => 'dashboard',
			),
			'pcm-banner'        => array(
				'title' => __( 'Consent Banner', 'consentguard' ),
				'view'  => 'banner',
			),
			'pcm-categories'    => array(
				'title' => __( 'Consent Categories', 'consentguard' ),
				'view'  => 'categories',
			),
			'pcm-analytics'     => array(
				'title' => __( 'Analytics', 'consentguard' ),
				'view'  => 'analytics',
			),
			'pcm-scripts'       => array(
				'title' => __( 'Script Manager', 'consentguard' ),
				'view'  => 'scripts',
			),
			'pcm-conflicts'     => array(
				'title' => __( 'Plugin Conflicts', 'consentguard' ),
				'view'  => 'conflicts',
			),
			'pcm-scanner'       => array(
				'title' => __( 'Cookie/Script Scanner', 'consentguard' ),
				'view'  => 'scanner',
			),
			'pcm-jurisdictions' => array(
				'title' => __( 'Jurisdiction Rules', 'consentguard' ),
				'view'  => 'jurisdictions',
			),
			'pcm-records'       => array(
				'title' => __( 'Consent Records', 'consentguard' ),
				'view'  => 'records',
			),
			'pcm-policies'      => array(
				'title' => __( 'Privacy Policies', 'consentguard' ),
				'view'  => 'policies',
			),
			'pcm-settings'      => array(
				'title' => __( 'Settings', 'consentguard' ),
				'view'  => 'settings',
			),
			'pcm-tools'         => array(
				'title' => __( 'Tools', 'consentguard' ),
				'view'  => 'tools',
			),
		);

		add_menu_page(
			__( 'ConsentGuard', 'consentguard' ),
			__( 'ConsentGuard', 'consentguard' ),
			Security::CAP_MANAGE,
			'pcm-dashboard',
			array( $this, 'render_page' ),
			'dashicons-privacy',
			81
		);

		foreach ( $this->pages as $slug => $page ) {
			add_submenu_page(
				'pcm-dashboard',
				$page['title'],
				$page['title'],
				Security::CAP_MANAGE,
				$slug,
				array( $this, 'render_page' )
			);
		}
	}

	/**
	 * Renders the current page's view file.
	 */
	public function render_page() {
		if ( ! current_user_can( Security::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'consentguard' ), 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only.
		$slug = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'pcm-dashboard';
		$view = isset( $this->pages[ $slug ] ) ? $this->pages[ $slug ]['view'] : 'dashboard';
		$file = PCM_PLUGIN_DIR . 'admin/views/' . $view . '.php';

		// Variables available to every view.
		$settings  = pcm_get_settings();
		$admin     = $this;
		$conflicts = $this->conflicts;
		$scanner   = $this->scanner;
		$storage   = $this->storage;

		echo '<div class="wrap pcm-wrap">';
		if ( is_readable( $file ) ) {
			include $file;
		} else {
			echo '<h1>' . esc_html__( 'ConsentGuard', 'consentguard' ) . '</h1>';
			echo '<p>' . esc_html__( 'View not found.', 'consentguard' ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Handles all settings form submissions.
	 */
	public function handle_save() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by check_admin_referer() inside Security::verify_admin_action() directly below; the sniff cannot trace through the helper.
		if ( ! isset( $_POST['pcm_action'] ) || 'save_settings' !== $_POST['pcm_action'] ) {
			return;
		}
		Security::verify_admin_action( 'pcm_save_settings' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized field-by-field in Settings::sanitize().
		$raw = isset( $_POST['pcm'] ) && is_array( $_POST['pcm'] ) ? wp_unslash( $_POST['pcm'] ) : array();

		// Checkbox-only fields need explicit false when absent; the section
		// list tells sanitize() which sections were present on the form.
		$sections = isset( $_POST['pcm_sections'] )
			? array_map( 'sanitize_key', (array) wp_unslash( $_POST['pcm_sections'] ) )
			: array_keys( $raw );

		foreach ( $sections as $section ) {
			if ( ! isset( $raw[ $section ] ) ) {
				$raw[ $section ] = array();
			}
		}

		// Blocker domain/allowlist textareas arrive as free text.
		if ( isset( $_POST['pcm_blocker_domains'] ) && isset( $raw['blocker'] ) ) {
			$domains = array();
			$lines   = explode( "\n", sanitize_textarea_field( wp_unslash( $_POST['pcm_blocker_domains'] ) ) );
			foreach ( $lines as $line ) {
				$parts = preg_split( '/\s+/', trim( $line ) );
				if ( empty( $parts[0] ) ) {
					continue;
				}
				$domains[ $parts[0] ] = isset( $parts[1] ) ? $parts[1] : 'analytics';
			}
			$raw['blocker']['domains'] = $domains;
		}
		if ( isset( $_POST['pcm_blocker_iframe_domains'] ) && isset( $raw['blocker'] ) ) {
			$iframes = array();
			$lines   = explode( "\n", sanitize_textarea_field( wp_unslash( $_POST['pcm_blocker_iframe_domains'] ) ) );
			foreach ( $lines as $line ) {
				$parts = preg_split( '/\s+/', trim( $line ) );
				if ( empty( $parts[0] ) ) {
					continue;
				}
				$iframes[ $parts[0] ] = isset( $parts[1] ) ? $parts[1] : 'functional';
			}
			$raw['blocker']['iframe_domains'] = $iframes;
		}
		if ( isset( $_POST['pcm_translations_json'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON decoded and sanitized in Settings::sanitize().
			$decoded             = json_decode( trim( wp_unslash( $_POST['pcm_translations_json'] ) ), true );
			$raw['translations'] = is_array( $decoded ) ? $decoded : array();
			if ( ! in_array( 'translations', $sections, true ) ) {
				$sections[] = 'translations';
			}
		}
		if ( isset( $_POST['pcm_blocker_allowlist'] ) && isset( $raw['blocker'] ) ) {
			$raw['blocker']['allowlist'] = array_filter(
				array_map( 'trim', explode( "\n", sanitize_textarea_field( wp_unslash( $_POST['pcm_blocker_allowlist'] ) ) ) )
			);
		}

		// Country → profile rules arrive as parallel arrays.
		if ( isset( $_POST['pcm_rule_countries'], $_POST['pcm_rule_profiles'] ) && isset( $raw['jurisdictions'] ) ) {
			$countries = array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['pcm_rule_countries'] ) );
			$profiles  = array_map( 'sanitize_key', (array) wp_unslash( $_POST['pcm_rule_profiles'] ) );
			$rules     = array();
			foreach ( $countries as $i => $country ) {
				$country = strtoupper( trim( $country ) );
				if ( preg_match( '/^[A-Z]{2}$/', $country ) && isset( $profiles[ $i ] ) ) {
					$rules[ $country ] = $profiles[ $i ];
				}
			}
			$raw['jurisdictions']['rules'] = $rules;
		}

		// Discovered cookies assigned to a category join the inventory.
		if ( isset( $_POST['pcm_discovered_assign'] ) && is_array( $_POST['pcm_discovered_assign'] ) ) {
			$discovered = (array) get_option( 'pcm_discovered_cookies', array() );
			$inventory  = (array) pcm_get_setting( 'cookies', array() );
			$changed    = false;
			foreach ( array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['pcm_discovered_assign'] ) ) as $name => $category ) {
				$category = pcm_sanitize_category_slug( $category );
				$name     = sanitize_text_field( $name );
				if ( '' === $category || 'skip' === $category || '' === $name ) {
					continue;
				}
				if ( 'dismiss' === $category ) {
					unset( $discovered[ $name ] );
					$changed = true;
					continue;
				}
				$inventory[ $category ][] = array(
					'name'        => $name,
					'duration'    => '',
					'description' => '',
				);
				unset( $discovered[ $name ] );
				$changed = true;
			}
			if ( $changed ) {
				update_option( 'pcm_discovered_cookies', $discovered, false );
				Settings::instance()->update_section( 'cookies', $inventory );
			}
		}

		// Cookie inventory textareas: "name | duration | description" lines.
		if ( isset( $_POST['pcm_cookie_inventory'] ) && is_array( $_POST['pcm_cookie_inventory'] ) ) {
			$inventory = array();
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per line below.
			foreach ( wp_unslash( $_POST['pcm_cookie_inventory'] ) as $slug => $text ) {
				$slug = pcm_sanitize_category_slug( $slug );
				if ( '' === $slug ) {
					continue;
				}
				$inventory[ $slug ] = array();
				foreach ( explode( "\n", sanitize_textarea_field( $text ) ) as $line ) {
					$parts = array_map( 'trim', explode( '|', $line, 3 ) );
					if ( '' === $parts[0] ) {
						continue;
					}
					$inventory[ $slug ][] = array(
						'name'        => $parts[0],
						'duration'    => $parts[1] ?? '',
						'description' => $parts[2] ?? '',
					);
				}
			}
			$raw['cookies'] = $inventory;
			if ( ! in_array( 'cookies', $sections, true ) ) {
				$sections[] = 'cookies';
			}
		}

		// New custom category from the categories screen.
		if ( ! empty( $_POST['pcm_new_category_slug'] ) && isset( $raw['categories'] ) ) {
			$new_slug = pcm_sanitize_category_slug( sanitize_text_field( wp_unslash( $_POST['pcm_new_category_slug'] ) ) );
			if ( '' !== $new_slug && ! isset( $raw['categories'][ $new_slug ] ) ) {
				$raw['categories'][ $new_slug ] = array(
					'label'       => isset( $_POST['pcm_new_category_label'] ) ? sanitize_text_field( wp_unslash( $_POST['pcm_new_category_label'] ) ) : $new_slug,
					'description' => isset( $_POST['pcm_new_category_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pcm_new_category_description'] ) ) : '',
					'required'    => false,
				);
			}
		}

		// Custom categories with an emptied label are deleted (built-ins stay).
		if ( isset( $raw['categories'] ) && is_array( $raw['categories'] ) ) {
			foreach ( $raw['categories'] as $slug => $category ) {
				if ( ! in_array( $slug, pcm_builtin_categories(), true ) && '' === trim( (string) ( $category['label'] ?? '' ) ) ) {
					unset( $raw['categories'][ $slug ] );
				}
			}
		}

		$sanitized = Settings::sanitize( $raw );
		$instance  = Settings::instance();
		foreach ( $sanitized as $section => $values ) {
			$instance->update_section( $section, $values );
		}

		wp_safe_redirect( add_query_arg( 'pcm-saved', '1', wp_get_referer() ?: admin_url( 'admin.php?page=pcm-dashboard' ) ) );
		exit;
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Handles conflict mitigate/ignore links.
	 */
	public function handle_conflict_action() {
		Security::verify_admin_action( 'pcm_conflict' );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- verified by check_admin_referer() inside Security::verify_admin_action() above.
		$id = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '';
		$op = isset( $_GET['op'] ) ? sanitize_key( wp_unslash( $_GET['op'] ) ) : '';
		// phpcs:enable

		$notice = 'done';
		switch ( $op ) {
			case 'mitigate':
				$result = $this->conflicts->apply_mitigation( $id );
				if ( is_wp_error( $result ) ) {
					$notice = 'manual';
				}
				break;
			case 'unmitigate':
				$this->conflicts->remove_mitigation( $id );
				break;
			case 'ignore':
				$this->conflicts->set_ignored( $id, true );
				break;
			case 'unignore':
				$this->conflicts->set_ignored( $id, false );
				break;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=pcm-conflicts&pcm-notice=' . $notice ) );
		exit;
	}

	/**
	 * Handles "Scan Now".
	 */
	public function handle_scan() {
		Security::verify_admin_action( 'pcm_scan' );

		$result = $this->scanner->scan();
		$notice = is_wp_error( $result ) ? 'scan-failed' : 'scan-done';

		$redirect = wp_get_referer() ?: admin_url( 'admin.php?page=pcm-scanner' );
		wp_safe_redirect( add_query_arg( 'pcm-notice', $notice, $redirect ) );
		exit;
	}

	/**
	 * Handles cookie policy generation.
	 */
	public function handle_generate_policy() {
		Security::verify_admin_action( 'pcm_generate_policy' );

		$generator = new Policy_Manager();
		Settings::instance()->update_section(
			'policies',
			array( 'generated_cookie_policy' => $generator->generate_cookie_policy() )
		);

		wp_safe_redirect( admin_url( 'admin.php?page=pcm-policies&pcm-notice=policy-generated' ) );
		exit;
	}

	/**
	 * Streams all consent records as CSV (proof-of-consent export).
	 */
	public function handle_export_records() {
		Security::verify_admin_action( 'pcm_export_records' );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=consent-records-' . gmdate( 'Ymd-His' ) . '.csv' );

		$out     = fopen( 'php://output', 'w' );
		$columns = array( 'created_at', 'consent_id', 'anonymous_id', 'action', 'necessary', 'functional', 'analytics', 'marketing', 'preferences', 'extra_categories', 'consent_version', 'policy_version', 'region', 'language' );
		fputcsv( $out, $columns );

		$page = 1;
		do {
			$batch       = $this->storage->get_records( $page, 100 );
			$batch_count = count( $batch['items'] );
			foreach ( $batch['items'] as $row ) {
				$line = array();
				foreach ( $columns as $column ) {
					$line[] = isset( $row[ $column ] ) ? $row[ $column ] : '';
				}
				fputcsv( $out, $line );
			}
			++$page;
		} while ( 100 === $batch_count );

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/**
	 * Streams a "Proof of consent" PDF for one consent record.
	 */
	public function handle_export_proof() {
		Security::verify_admin_action( 'pcm_export_proof' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified by check_admin_referer() inside Security::verify_admin_action() above.
		$uuid   = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '';
		$record = wp_is_uuid( $uuid ) ? $this->storage->find_by_uuid( $uuid ) : null;
		if ( ! $record ) {
			wp_safe_redirect( admin_url( 'admin.php?page=pcm-records&pcm-notice=record-not-found' ) );
			exit;
		}

		$pdf = $this->build_proof_pdf( $record );

		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename=proof-of-consent-' . substr( $record['consent_id'], 0, 8 ) . '.pdf' );
		header( 'Content-Length: ' . strlen( $pdf ) );
		echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binary PDF payload.
		exit;
	}

	/**
	 * Builds the proof-of-consent PDF document for a record.
	 *
	 * @param array $record Consent record row.
	 * @return string Raw PDF bytes.
	 */
	public function build_proof_pdf( array $record ) {
		$settings   = pcm_get_settings();
		$categories = $settings['categories'];

		$extra = array();
		if ( ! empty( $record['extra_categories'] ) ) {
			$decoded = json_decode( (string) $record['extra_categories'], true );
			if ( is_array( $decoded ) ) {
				$extra = $decoded;
			}
		}

		$granted = array();
		$denied  = array();
		foreach ( $categories as $slug => $category ) {
			if ( ! empty( $category['required'] ) ) {
				continue;
			}
			$state = in_array( $slug, pcm_builtin_categories(), true )
				? ! empty( $record[ $slug ] )
				: ! empty( $extra[ $slug ] );
			if ( $state ) {
				$granted[] = $slug;
			} else {
				$denied[] = $slug;
			}
		}

		if ( 'reject_all' === $record['action'] || empty( $granted ) ) {
			$status = __( 'Rejected', 'consentguard' );
		} elseif ( empty( $denied ) ) {
			$status = __( 'Accepted', 'consentguard' );
		} else {
			$status = __( 'Customized', 'consentguard' );
		}

		// Services managed by this plugin, grouped by category.
		$services = array();
		$managed  = array(
			array( 'ga4', __( 'Google Analytics 4', 'consentguard' ) ),
			array( 'clarity', __( 'Microsoft Clarity', 'consentguard' ) ),
			array( 'cloudflare', __( 'Cloudflare Web Analytics', 'consentguard' ) ),
			array( 'gtm', __( 'Google Tag Manager', 'consentguard' ) ),
		);
		foreach ( $managed as $entry ) {
			list( $key, $label ) = $entry;
			if ( ! empty( $settings[ $key ]['enabled'] ) ) {
				$services[ $settings[ $key ]['category'] ][] = $label;
			}
		}
		foreach ( (array) $settings['custom_scripts'] as $script ) {
			if ( ! empty( $script['enabled'] ) ) {
				$services[ $script['category'] ][] = $script['name'];
			}
		}

		$primary   = sanitize_hex_color( $settings['banner']['primary_color'] ?? '#1a73e8' ) ?: '#1a73e8';
		$inventory = (array) ( $settings['cookies'] ?? array() );
		$domain    = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		$pdf = new Pdf_Writer( __( 'Proof of consent', 'consentguard' ) );

		// Branded header band: project name + domain.
		$pdf->header_band( 'ConsentGuard', $primary, $domain );

		$pdf->title( __( 'Proof of consent', 'consentguard' ) );
		$pdf->hr();

		$pdf->field( __( 'Consented domain', 'consentguard' ), $domain );
		$pdf->field( __( 'Consent date', 'consentguard' ), $record['created_at'] . ' UTC+00:00' );
		$pdf->field( __( 'Consent ID', 'consentguard' ), $record['consent_id'] );
		$pdf->field( __( 'Anonymous visitor ID', 'consentguard' ), $record['anonymous_id'] ?: __( 'Not provided', 'consentguard' ) );
		$pdf->field( __( 'Region / profile', 'consentguard' ), $record['region'] ?: __( 'Not available', 'consentguard' ) );
		$pdf->field( __( 'Language', 'consentguard' ), $record['language'] ?: __( 'Not available', 'consentguard' ) );
		$pdf->field( __( 'IP address', 'consentguard' ), __( 'Not stored (privacy by design)', 'consentguard' ) );
		$pdf->field( __( 'Consent status', 'consentguard' ), $status );
		$pdf->field( __( 'Recorded action', 'consentguard' ), $record['action'] );
		$pdf->field( __( 'Consent version', 'consentguard' ), $record['consent_version'] ?: '-' );
		$pdf->field( __( 'Policy version', 'consentguard' ), $record['policy_version'] ?: '-' );

		$pdf->space( 10 );
		$pdf->heading( __( 'Category-wise consent status', 'consentguard' ), $primary );

		foreach ( $categories as $slug => $category ) {
			if ( ! empty( $category['required'] ) ) {
				$state_label = __( 'Always Active', 'consentguard' );
				$state_color = '#008000';
			} elseif ( in_array( $slug, $granted, true ) ) {
				$state_label = __( 'Accepted', 'consentguard' );
				$state_color = '#008000';
			} else {
				$state_label = __( 'Rejected', 'consentguard' );
				$state_color = '#b3261e';
			}

			$pdf->ensure_room( 80 );
			$pdf->space( 10 );
			$pdf->paragraph( $category['label'], 13, true );
			$pdf->space( 1 );
			$pdf->paragraph( $state_label, 10.5, true, 0, $state_color );
			if ( ! empty( $category['description'] ) ) {
				$pdf->space( 2 );
				$pdf->paragraph( $category['description'], 9.5, false, 0, '#666666' );
			}

			// Cookie entries (Cookie / Duration / Description) in shaded boxes.
			foreach ( (array) ( $inventory[ $slug ] ?? array() ) as $cookie ) {
				$desc_lines = $pdf->measure_lines( (string) ( $cookie['description'] ?? '' ), 9, 96 );
				$box_height = 44 + $desc_lines * 13;
				$pdf->ensure_room( $box_height + 10 );
				$pdf->space( 8 );
				$pdf->box( $box_height, '#f4f4f4', 6 );
				$pdf->space( 8 );
				$pdf->paragraph( __( 'Cookie', 'consentguard' ) . ':      ' . ( $cookie['name'] ?? '' ), 9.5, true, 16 );
				$pdf->paragraph( __( 'Duration', 'consentguard' ) . ':   ' . ( '' !== ( $cookie['duration'] ?? '' ) ? $cookie['duration'] : '-' ), 9, false, 16 );
				$pdf->paragraph( __( 'Description', 'consentguard' ) . ': ' . ( '' !== ( $cookie['description'] ?? '' ) ? $cookie['description'] : '-' ), 9, false, 16 );
				$pdf->space( 8 );
			}

			if ( ! empty( $services[ $slug ] ) ) {
				$pdf->space( 4 );
				$pdf->paragraph(
					__( 'Managed services:', 'consentguard' ) . ' ' . implode( ', ', array_unique( $services[ $slug ] ) ),
					9.5,
					false,
					0,
					'#666666'
				);
			}
			$pdf->space( 8 );
			$pdf->hr();
		}

		$pdf->space( 14 );
		$pdf->paragraph(
			__( 'This document was generated by ConsentGuard from the consent record stored on the website\'s own server. Consent records contain no IP addresses or direct identifiers; the anonymous visitor ID is generated in the visitor\'s browser.', 'consentguard' ),
			8.5,
			false,
			0,
			'#888888'
		);
		$pdf->space( 2 );
		$pdf->paragraph(
			sprintf(
				/* translators: 1: site URL, 2: generation time */
				__( 'Generated by ConsentGuard for %1$s on %2$s UTC.', 'consentguard' ),
				home_url(),
				gmdate( 'Y-m-d H:i:s' )
			),
			8.5,
			false,
			0,
			'#888888'
		);

		return $pdf->render();
	}

	/**
	 * Downloads the full configuration as JSON (agency workflow).
	 */
	public function handle_export_settings() {
		Security::verify_admin_action( 'pcm_export_settings' );

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=ConsentGuard-settings-' . gmdate( 'Ymd' ) . '.json' );
		echo wp_json_encode( pcm_get_settings(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download.
		exit;
	}

	/**
	 * Imports a configuration JSON. Every value passes through
	 * Settings::sanitize() — importing is exactly as safe as typing the
	 * values into the forms.
	 */
	public function handle_import_settings() {
		Security::verify_admin_action( 'pcm_import_settings' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- nonce verified by check_admin_referer() inside Security::verify_admin_action() above; JSON decoded and sanitized field-by-field below.
		$json    = isset( $_POST['pcm_import_json'] ) ? trim( wp_unslash( $_POST['pcm_import_json'] ) ) : '';
		$decoded = json_decode( $json, true );

		if ( ! is_array( $decoded ) || empty( $decoded ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=pcm-tools&pcm-notice=import-failed' ) );
			exit;
		}

		$sanitized = Settings::sanitize( $decoded );
		$instance  = Settings::instance();
		foreach ( $sanitized as $section => $values ) {
			$instance->update_section( $section, $values );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=pcm-tools&pcm-notice=import-done' ) );
		exit;
	}

	/**
	 * Deletes consent records matching a consent/anonymous ID.
	 */
	public function handle_delete_record() {
		Security::verify_admin_action( 'pcm_delete_record' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by check_admin_referer() inside Security::verify_admin_action() above.
		$uuid    = isset( $_POST['pcm_record_uuid'] ) ? sanitize_text_field( wp_unslash( $_POST['pcm_record_uuid'] ) ) : '';
		$deleted = wp_is_uuid( $uuid ) ? $this->storage->delete_by_uuid( $uuid ) : 0;

		wp_safe_redirect( admin_url( 'admin.php?page=pcm-records&pcm-notice=' . ( $deleted > 0 ? 'record-deleted' : 'record-not-found' ) ) );
		exit;
	}

	/**
	 * Admin assets, loaded only on plugin pages.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, 'pcm-' ) ) {
			return;
		}
		wp_enqueue_style( 'pcm-admin', PCM_PLUGIN_URL . 'admin/css/admin.css', array(), PCM_VERSION );
		wp_enqueue_script( 'pcm-admin', PCM_PLUGIN_URL . 'admin/js/admin.js', array(), PCM_VERSION, true );
		wp_localize_script(
			'pcm-admin',
			'PCMAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'pcm_admin' ),
			)
		);
	}

	/**
	 * Renders shared form scaffolding (nonce + action + section markers).
	 *
	 * @param string[] $sections Sections contained in this form.
	 */
	public static function form_open( array $sections ) {
		echo '<form method="post" action="">';
		wp_nonce_field( 'pcm_save_settings', '_pcm_nonce' );
		echo '<input type="hidden" name="pcm_action" value="save_settings" />';
		foreach ( $sections as $section ) {
			printf( '<input type="hidden" name="pcm_sections[]" value="%s" />', esc_attr( $section ) );
		}
	}

	/**
	 * Closes the form with a submit button.
	 */
	public static function form_close() {
		submit_button( __( 'Save Changes', 'consentguard' ) );
		echo '</form>';
	}

	/**
	 * Prints the "saved" notice when applicable.
	 */
	public static function maybe_notice() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display-only notices.
		if ( isset( $_GET['pcm-saved'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'consentguard' ) . '</p></div>';
		}
		if ( isset( $_GET['pcm-notice'] ) ) {
			$key      = sanitize_key( wp_unslash( $_GET['pcm-notice'] ) );
			$messages = array(
				'done'             => array( 'success', __( 'Conflict updated.', 'consentguard' ) ),
				'manual'           => array( 'warning', __( 'Automatic conflict resolution is not available for this plugin. Please disable the tracking feature manually in that plugin\'s settings.', 'consentguard' ) ),
				'scan-done'        => array( 'success', __( 'Scan completed.', 'consentguard' ) ),
				'scan-failed'      => array( 'error', __( 'Scan failed. Check that your site can reach its own homepage (loopback requests).', 'consentguard' ) ),
				'policy-generated' => array( 'success', __( 'Cookie policy draft generated. Review it below and have it checked by your legal team before publishing.', 'consentguard' ) ),
				'record-deleted'   => array( 'success', __( 'Matching consent records were deleted.', 'consentguard' ) ),
				'record-not-found' => array( 'warning', __( 'No consent records matched that ID (a full UUID is required).', 'consentguard' ) ),
				'import-done'      => array( 'success', __( 'Settings imported. Every value was sanitized on the way in — review the screens to confirm.', 'consentguard' ) ),
				'import-failed'    => array( 'error', __( 'Import failed: the provided text is not valid JSON.', 'consentguard' ) ),
			);
			if ( isset( $messages[ $key ] ) ) {
				printf(
					'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
					esc_attr( $messages[ $key ][0] ),
					esc_html( $messages[ $key ][1] )
				);
			}
		}
		// phpcs:enable
	}
}
