<?php
/**
 * Main plugin orchestrator.
 *
 * @package PCM
 */

namespace PCM;

defined( 'ABSPATH' ) || exit;

/**
 * Wires all modules together. Each module has a single responsibility;
 * this class only instantiates them and registers cross-cutting hooks.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Module registry.
	 *
	 * @var array<string, object>
	 */
	private $modules = array();

	/**
	 * Returns the shared instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	/**
	 * Private constructor; use instance().
	 */
	private function __construct() {}

	/**
	 * Instantiates modules and registers hooks.
	 */
	private function boot() {
		load_plugin_textdomain( 'privacypress', false, dirname( PCM_PLUGIN_BASENAME ) . '/languages' );

		$settings = Settings::instance();
		$storage  = new Consent_Storage();

		$this->modules['settings']        = $settings;
		$this->modules['storage']         = $storage;
		$this->modules['consent']         = new Consent_Manager( $storage );
		$this->modules['geolocation']     = new Geolocation_Manager();
		$this->modules['script_manager']  = new Script_Manager();
		$this->modules['script_blocker']  = new Script_Blocker();
		$this->modules['analytics']       = new Analytics_Manager();
		$this->modules['custom_scripts']  = new Custom_Script_Manager();
		$this->modules['plugin_detector'] = new Plugin_Detector();
		$this->modules['conflicts']       = new Plugin_Conflict_Manager( $this->modules['plugin_detector'] );
		$this->modules['duplicates']      = new Duplicate_Tracking_Detector( $this->modules['plugin_detector'] );
		$this->modules['privacy']         = new Privacy_Manager( $storage );
		$this->modules['policies']        = new Policy_Manager();
		$this->modules['rest']            = new Rest_Api( $storage, $this->modules['duplicates'] );
		$this->modules['security']        = new Security();

		foreach ( $this->modules as $module ) {
			if ( method_exists( $module, 'register' ) ) {
				$module->register();
			}
		}

		if ( is_admin() ) {
			$admin = new Admin\Admin( $this->modules['conflicts'], $this->modules['duplicates'], $storage );
			$admin->register();
			$this->modules['admin'] = $admin;
		} else {
			$public = new Frontend( $this->modules['consent'], $this->modules['geolocation'] );
			$public->register();
			$this->modules['public'] = $public;
		}

		$this->load_integrations();

		add_action( 'pcm_cleanup_consents', array( $storage, 'cleanup_expired' ) );
		add_action( 'admin_init', array( Activator::class, 'maybe_upgrade' ) );

		/**
		 * Fires after every plugin module has been registered.
		 *
		 * @param Plugin $plugin Plugin instance.
		 */
		do_action( 'pcm_loaded', $this );
	}

	/**
	 * Loads third-party integration shims for conflict mitigation.
	 */
	private function load_integrations() {
		$integrations = array(
			'site-kit.php',
			'microsoft-clarity.php',
			'cloudflare.php',
			'google-analytics.php',
			'google-tag-manager.php',
			'wp-consent-api.php',
			'generic.php',
		);
		foreach ( $integrations as $file ) {
			$path = PCM_PLUGIN_DIR . 'integrations/' . $file;
			if ( is_readable( $path ) ) {
				require_once $path;
			}
		}
	}

	/**
	 * Returns a module by key.
	 *
	 * @param string $key Module key.
	 * @return object|null
	 */
	public function module( $key ) {
		return isset( $this->modules[ $key ] ) ? $this->modules[ $key ] : null;
	}
}
