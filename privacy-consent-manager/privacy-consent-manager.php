<?php
/**
 * Plugin Name:       Privacy & Consent Manager
 * Plugin URI:        https://example.com/privacy-consent-manager
 * Description:       Centralized privacy, consent, analytics and tracking management platform. Manages GA4, Google Consent Mode v2, Microsoft Clarity, Cloudflare Web Analytics, GTM and custom scripts, and detects duplicate tracking injected by other plugins.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Privacy & Consent Manager Contributors
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       privacy-consent-manager
 * Domain Path:       /languages
 *
 * @package PCM
 */

defined( 'ABSPATH' ) || exit;

define( 'PCM_VERSION', '1.0.0' );
define( 'PCM_DB_VERSION', '1.0.0' );
define( 'PCM_PLUGIN_FILE', __FILE__ );
define( 'PCM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PCM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PCM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader for PCM\ namespaced classes.
 *
 * Maps PCM\Foo_Bar          -> includes/class-foo-bar.php
 *      PCM\Admin\Foo_Bar    -> admin/class-foo-bar.php
 */
spl_autoload_register(
	static function ( $class ) {
		if ( 0 !== strpos( $class, 'PCM\\' ) ) {
			return;
		}

		$relative = substr( $class, 4 ); // Strip "PCM\".

		// "public" is a reserved word, so the frontend class lives in
		// public/class-public.php but is named PCM\Frontend.
		if ( 'Frontend' === $relative ) {
			require PCM_PLUGIN_DIR . 'public/class-public.php';
			return;
		}

		$parts = explode( '\\', $relative );
		$name     = array_pop( $parts );
		$file     = 'class-' . str_replace( '_', '-', strtolower( $name ) ) . '.php';

		$map = array(
			''      => 'includes/',
			'Admin' => 'admin/',
		);

		$ns  = implode( '\\', $parts );
		$dir = isset( $map[ $ns ] ) ? $map[ $ns ] : 'includes/';

		$path = PCM_PLUGIN_DIR . $dir . $file;
		if ( is_readable( $path ) ) {
			require $path;
		}
	}
);

require_once PCM_PLUGIN_DIR . 'includes/helpers.php';

register_activation_hook( __FILE__, array( 'PCM\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'PCM\Deactivator', 'deactivate' ) );

/**
 * Returns the shared plugin instance.
 *
 * @return PCM\Plugin
 */
function pcm() {
	return PCM\Plugin::instance();
}

add_action( 'plugins_loaded', 'pcm', 5 );
