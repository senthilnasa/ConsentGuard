<?php
/**
 * Central gate deciding whether any managed script may load.
 *
 * @package PCM
 */

namespace PCM;

defined( 'ABSPATH' ) || exit;

/**
 * Every integration and custom script asks this class one question:
 * "may script X in category Y load for this request?"
 *
 * On the cached frontend the answer is always "render blocked, let the
 * client decide" — real consent evaluation happens in JavaScript so page
 * caching never leaks one visitor's consent into another visitor's page.
 */
class Script_Manager {

	/**
	 * Registers hooks.
	 */
	public function register() {}

	/**
	 * Whether a script should be emitted at all (enabled + valid config).
	 * Consent gating happens client-side; this only filters configuration.
	 *
	 * @param bool   $configured Integration is enabled and configured.
	 * @param string $script_id  Identifier, e.g. 'ga4', 'clarity', custom id.
	 * @param string $category   Consent category slug.
	 * @return bool
	 */
	public function should_output( $configured, $script_id, $category ) {
		/**
		 * Filters whether a managed script should be output (in blocked form).
		 *
		 * Note: returning true does NOT bypass consent — the script is still
		 * rendered as type="text/plain" and only executed client-side after
		 * the visitor grants the category.
		 *
		 * @param bool   $configured Enabled and configured.
		 * @param string $script_id  Script identifier.
		 * @param string $category   Consent category.
		 */
		return (bool) apply_filters( 'pcm_should_load_script', (bool) $configured, $script_id, $category );
	}

	/**
	 * Renders an inline script in consent-blocked form.
	 *
	 * The browser ignores type="text/plain"; consent-manager.js re-creates
	 * the node with a real type once the category is granted.
	 *
	 * @param string $js        JavaScript body (no <script> tags).
	 * @param string $script_id Identifier for debugging/events.
	 * @param string $category  Required consent category.
	 * @return string
	 */
	public static function blocked_inline( $js, $script_id, $category ) {
		return sprintf(
			'<script type="text/plain" data-pcm-managed="1" data-pcm-id="%s" data-pcm-category="%s">%s</script>' . "\n",
			esc_attr( $script_id ),
			esc_attr( $category ),
			$js // Inline JS is authored by this plugin or an unfiltered_html admin; not HTML-escaped or it would break the code.
		);
	}

	/**
	 * Renders an external script in consent-blocked form.
	 *
	 * @param string $src        Script URL.
	 * @param string $script_id  Identifier.
	 * @param string $category   Required consent category.
	 * @param array  $attributes Extra attributes (defer, async, data-*).
	 * @return string
	 */
	public static function blocked_external( $src, $script_id, $category, array $attributes = array() ) {
		$attrs = '';
		foreach ( $attributes as $name => $value ) {
			$name = preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $name ) );
			if ( '' === $name ) {
				continue;
			}
			$attrs .= true === $value
				? sprintf( ' data-pcm-attr-%s', esc_attr( $name ) )
				: sprintf( ' data-pcm-attr-%s="%s"', esc_attr( $name ), esc_attr( $value ) );
		}
		// phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedScript -- deliberately NOT enqueued: this is an inert consent-blocked template (type="text/plain", src stashed in data-pcm-src) that the browser ignores until the visitor grants the category; wp_enqueue_script() cannot express consent-gated execution.
		return sprintf(
			'<script type="text/plain" data-pcm-managed="1" data-pcm-id="%s" data-pcm-category="%s" data-pcm-src="%s"%s></script>' . "\n",
			esc_attr( $script_id ),
			esc_attr( $category ),
			esc_url( $src ),
			$attrs
		);
		// phpcs:enable WordPress.WP.EnqueuedResources.NonEnqueuedScript
	}
}
