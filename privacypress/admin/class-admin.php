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
use PCM\Policy_Manager;
use PCM\Security;
use PCM\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the "PrivacyPress" menu, renders view files and processes
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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Menu structure.
	 */
	public function register_menu() {
		$this->pages = array(
			'pcm-dashboard'     => array(
				'title' => __( 'Dashboard', 'privacypress' ),
				'view'  => 'dashboard',
			),
			'pcm-banner'        => array(
				'title' => __( 'Consent Banner', 'privacypress' ),
				'view'  => 'banner',
			),
			'pcm-categories'    => array(
				'title' => __( 'Consent Categories', 'privacypress' ),
				'view'  => 'categories',
			),
			'pcm-analytics'     => array(
				'title' => __( 'Analytics', 'privacypress' ),
				'view'  => 'analytics',
			),
			'pcm-scripts'       => array(
				'title' => __( 'Script Manager', 'privacypress' ),
				'view'  => 'scripts',
			),
			'pcm-conflicts'     => array(
				'title' => __( 'Plugin Conflicts', 'privacypress' ),
				'view'  => 'conflicts',
			),
			'pcm-scanner'       => array(
				'title' => __( 'Cookie/Script Scanner', 'privacypress' ),
				'view'  => 'scanner',
			),
			'pcm-jurisdictions' => array(
				'title' => __( 'Jurisdiction Rules', 'privacypress' ),
				'view'  => 'jurisdictions',
			),
			'pcm-records'       => array(
				'title' => __( 'Consent Records', 'privacypress' ),
				'view'  => 'records',
			),
			'pcm-policies'      => array(
				'title' => __( 'Privacy Policies', 'privacypress' ),
				'view'  => 'policies',
			),
			'pcm-settings'      => array(
				'title' => __( 'Settings', 'privacypress' ),
				'view'  => 'settings',
			),
			'pcm-tools'         => array(
				'title' => __( 'Tools', 'privacypress' ),
				'view'  => 'tools',
			),
		);

		add_menu_page(
			__( 'PrivacyPress', 'privacypress' ),
			__( 'PrivacyPress', 'privacypress' ),
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
			wp_die( esc_html__( 'You are not allowed to access this page.', 'privacypress' ), 403 );
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
			echo '<h1>' . esc_html__( 'PrivacyPress', 'privacypress' ) . '</h1>';
			echo '<p>' . esc_html__( 'View not found.', 'privacypress' ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Handles all settings form submissions.
	 */
	public function handle_save() {
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

		// New custom category from the categories screen.
		if ( ! empty( $_POST['pcm_new_category_slug'] ) && isset( $raw['categories'] ) ) {
			$new_slug = pcm_sanitize_category_slug( wp_unslash( $_POST['pcm_new_category_slug'] ) );
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
	}

	/**
	 * Handles conflict mitigate/ignore links.
	 */
	public function handle_conflict_action() {
		Security::verify_admin_action( 'pcm_conflict' );

		$id = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '';
		$op = isset( $_GET['op'] ) ? sanitize_key( wp_unslash( $_GET['op'] ) ) : '';

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
			$batch = $this->storage->get_records( $page, 100 );
			foreach ( $batch['items'] as $row ) {
				$line = array();
				foreach ( $columns as $column ) {
					$line[] = isset( $row[ $column ] ) ? $row[ $column ] : '';
				}
				fputcsv( $out, $line );
			}
			$page++;
		} while ( count( $batch['items'] ) === 100 );

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/**
	 * Deletes consent records matching a consent/anonymous ID.
	 */
	public function handle_delete_record() {
		Security::verify_admin_action( 'pcm_delete_record' );

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
		submit_button( __( 'Save Changes', 'privacypress' ) );
		echo '</form>';
	}

	/**
	 * Prints the "saved" notice when applicable.
	 */
	public static function maybe_notice() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display-only notices.
		if ( isset( $_GET['pcm-saved'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'privacypress' ) . '</p></div>';
		}
		if ( isset( $_GET['pcm-notice'] ) ) {
			$key      = sanitize_key( wp_unslash( $_GET['pcm-notice'] ) );
			$messages = array(
				'done'             => array( 'success', __( 'Conflict updated.', 'privacypress' ) ),
				'manual'           => array( 'warning', __( 'Automatic conflict resolution is not available for this plugin. Please disable the tracking feature manually in that plugin\'s settings.', 'privacypress' ) ),
				'scan-done'        => array( 'success', __( 'Scan completed.', 'privacypress' ) ),
				'scan-failed'      => array( 'error', __( 'Scan failed. Check that your site can reach its own homepage (loopback requests).', 'privacypress' ) ),
				'policy-generated' => array( 'success', __( 'Cookie policy draft generated. Review it below and have it checked by your legal team before publishing.', 'privacypress' ) ),
				'record-deleted'   => array( 'success', __( 'Matching consent records were deleted.', 'privacypress' ) ),
				'record-not-found' => array( 'warning', __( 'No consent records matched that ID (a full UUID is required).', 'privacypress' ) ),
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
