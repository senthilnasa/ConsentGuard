<?php
/**
 * PHPUnit bootstrap for unit tests.
 *
 * These are true unit tests: WordPress functions used by the tested code
 * are shimmed below, so the suite runs with plain PHPUnit and no WordPress
 * install. Integration tests against a real WordPress (wp-env / WP test
 * suite) are described in docs/TESTING.md.
 *
 * @package PCM
 */

define( 'ABSPATH', __DIR__ . '/fake-abspath/' );
define( 'PCM_PLUGIN_DIR', dirname( __DIR__, 2 ) . '/' );
define( 'PCM_PLUGIN_URL', 'https://example.test/wp-content/plugins/privacy-consent-manager/' );
define( 'PCM_VERSION', '1.0.0-test' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

/* -------------------------------------------------------------------- *
 * In-memory option store
 * -------------------------------------------------------------------- */

$GLOBALS['pcm_test_options']   = array();
$GLOBALS['pcm_test_filters']   = array();
$GLOBALS['pcm_test_user_caps'] = array( 'manage_options' => true, 'unfiltered_html' => true );

function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['pcm_test_options'] ) ? $GLOBALS['pcm_test_options'][ $name ] : $default;
}
function update_option( $name, $value, $autoload = null ) {
	$GLOBALS['pcm_test_options'][ $name ] = $value;
	return true;
}
function add_option( $name, $value, $deprecated = '', $autoload = null ) {
	return update_option( $name, $value );
}
function delete_option( $name ) {
	unset( $GLOBALS['pcm_test_options'][ $name ] );
	return true;
}
function get_transient( $name ) {
	return get_option( '_transient_' . $name );
}
function set_transient( $name, $value, $ttl = 0 ) {
	return update_option( '_transient_' . $name, $value );
}
function delete_transient( $name ) {
	return delete_option( '_transient_' . $name );
}
function get_site_option( $name, $default = false ) {
	return get_option( $name, $default );
}

/* -------------------------------------------------------------------- *
 * Hooks (pass-through)
 * -------------------------------------------------------------------- */

function apply_filters( $tag, $value ) {
	if ( isset( $GLOBALS['pcm_test_filters'][ $tag ] ) ) {
		$args    = func_get_args();
		$args    = array_slice( $args, 1 );
		$value   = call_user_func_array( $GLOBALS['pcm_test_filters'][ $tag ], $args );
	}
	return $value;
}
function add_filter( $tag, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['pcm_test_filters'][ $tag ] = $callback;
	return true;
}
function do_action( $tag, ...$args ) {}
function add_action( $tag, $callback, $priority = 10, $args = 1 ) {
	return true;
}
function did_action( $tag ) {
	return 0;
}

/* -------------------------------------------------------------------- *
 * Sanitization / escaping
 * -------------------------------------------------------------------- */

function sanitize_text_field( $str ) {
	$str = (string) $str;
	$str = preg_replace( '/<[^>]*>/', '', $str );
	$str = preg_replace( '/[\r\n\t ]+/', ' ', $str );
	return trim( $str );
}
function sanitize_textarea_field( $str ) {
	$str = preg_replace( '/<[^>]*>/', '', (string) $str );
	return trim( $str );
}
function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}
function sanitize_email( $email ) {
	return filter_var( trim( (string) $email ), FILTER_VALIDATE_EMAIL ) ?: '';
}
function sanitize_hex_color( $color ) {
	$color = (string) $color;
	return preg_match( '/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $color ) ? $color : null;
}
function esc_url_raw( $url ) {
	return filter_var( (string) $url, FILTER_VALIDATE_URL ) ? (string) $url : '';
}
function esc_url( $url ) {
	return esc_url_raw( $url );
}
function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}
function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}
function esc_textarea( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}
function absint( $value ) {
	return abs( (int) $value );
}
function wp_kses_post( $text ) {
	return preg_replace( '/<script\b[^>]*>.*?<\/script>/is', '', (string) $text );
}
function wp_unslash( $value ) {
	return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( (string) $value );
}

/* -------------------------------------------------------------------- *
 * Misc WP utilities
 * -------------------------------------------------------------------- */

function wp_json_encode( $data, $options = 0, $depth = 512 ) {
	return json_encode( $data, $options, $depth );
}
function wp_generate_uuid4() {
	return sprintf(
		'%04x%04x-%04x-4%03x-%04x-%04x%04x%04x',
		mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
		mt_rand( 0, 0xffff ),
		mt_rand( 0, 0xfff ),
		mt_rand( 0, 0x3fff ) | 0x8000,
		mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
	);
}
function wp_is_uuid( $uuid, $version = null ) {
	return is_string( $uuid ) && (bool) preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', strtolower( $uuid ) );
}
function wp_is_numeric_array( $data ) {
	if ( ! is_array( $data ) ) {
		return false;
	}
	$keys = array_keys( $data );
	return $keys === array_filter( $keys, 'is_int' );
}
function wp_parse_url( $url, $component = -1 ) {
	return parse_url( (string) $url, $component );
}
function current_time( $type, $gmt = 0 ) {
	return 'mysql' === $type ? gmdate( 'Y-m-d H:i:s' ) : time();
}
function home_url( $path = '' ) {
	return 'https://example.test' . $path;
}
function get_permalink( $id ) {
	return 'https://example.test/?p=' . (int) $id;
}
function rest_url( $path = '' ) {
	return 'https://example.test/wp-json/' . ltrim( $path, '/' );
}
function get_locale() {
	return 'en_US';
}
function current_user_can( $cap ) {
	return ! empty( $GLOBALS['pcm_test_user_caps'][ $cap ] );
}
function is_admin() {
	return false;
}
function is_multisite() {
	return false;
}
function is_customize_preview() {
	return false;
}
function wp_doing_ajax() {
	return false;
}
function wp_doing_cron() {
	return false;
}
function wp_is_json_request() {
	return false;
}

/* Translation shims. */
function __( $text, $domain = 'default' ) {
	return $text;
}
function _e( $text, $domain = 'default' ) {
	echo $text;
}
function esc_html__( $text, $domain = 'default' ) {
	return esc_html( $text );
}
function esc_attr__( $text, $domain = 'default' ) {
	return esc_attr( $text );
}

if ( ! function_exists( 'str_ends_with' ) ) {
	function str_ends_with( $haystack, $needle ) {
		return '' === $needle || substr( $haystack, -strlen( $needle ) ) === $needle;
	}
}

/* Minimal WP_Error for sanitize_record tests. */
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public $code;
		public $message;
		public $data;
		public function __construct( $code = '', $message = '', $data = null ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}
		public function get_error_code() {
			return $this->code;
		}
		public function get_error_message() {
			return $this->message;
		}
	}
}
function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

/* -------------------------------------------------------------------- *
 * Load the code under test
 * -------------------------------------------------------------------- */

require_once PCM_PLUGIN_DIR . 'includes/helpers.php';
require_once PCM_PLUGIN_DIR . 'includes/class-settings.php';
require_once PCM_PLUGIN_DIR . 'includes/class-script-blocker.php';
require_once PCM_PLUGIN_DIR . 'includes/class-consent-storage.php';
require_once PCM_PLUGIN_DIR . 'includes/class-consent-manager.php';
require_once PCM_PLUGIN_DIR . 'includes/class-geolocation-manager.php';

/**
 * Resets shared state between tests.
 */
function pcm_test_reset() {
	$GLOBALS['pcm_test_options'] = array();
	$GLOBALS['pcm_test_filters'] = array();
	$_COOKIE                     = array();
	$_SERVER['REMOTE_ADDR']      = '127.0.0.1';
	PCM\Settings::instance()->flush_cache();
}
