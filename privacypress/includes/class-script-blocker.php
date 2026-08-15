<?php
/**
 * Automatic script-blocking engine.
 *
 * @package PCM
 */

namespace PCM;

defined( 'ABSPATH' ) || exit;

/**
 * Rewrites third-party tracking <script> tags emitted by themes or other
 * plugins into consent-blocked form (type="text/plain") using an output
 * buffer. consent-manager.js executes them client-side once — and only
 * once — the visitor grants the matching category.
 *
 * Design constraints:
 * - Cache-safe: the rewritten page is identical for every visitor, so page
 *   caches and CDNs can cache it freely. Consent is evaluated in the browser.
 * - Fail-open for the page: any parsing problem returns the original markup.
 *   Never break the site to block a tracker.
 * - Allowlist beats denylist.
 */
class Script_Blocker {

	/**
	 * Inline-code signatures mapped to categories. Used when a tracking
	 * snippet is inlined rather than loaded from a known domain.
	 *
	 * @var array<string,string>
	 */
	private $inline_signatures = array(
		'www.googletagmanager.com/gtag/js'  => 'analytics',
		'www.googletagmanager.com/gtm.js'   => 'analytics',
		'www.clarity.ms/tag/'               => 'analytics',
		'connect.facebook.net'              => 'marketing',
		'static.cloudflareinsights.com'     => 'analytics',
	);

	/**
	 * Registers the output buffer on the frontend only.
	 */
	public function register() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || wp_is_json_request() ) {
			return;
		}
		add_action( 'template_redirect', array( $this, 'start_buffer' ), 1 );
	}

	/**
	 * Starts buffering unless the request must not be touched.
	 */
	public function start_buffer() {
		if ( ! pcm_get_setting( 'blocker.enabled', true ) ) {
			return;
		}
		if ( is_feed() || is_robots() || is_embed() || pcm_is_elementor_editor() || is_customize_preview() ) {
			return;
		}

		/**
		 * Filters whether the automatic script blocker runs for this request.
		 *
		 * @param bool $enabled Run the blocker.
		 */
		if ( ! apply_filters( 'pcm_script_blocker_enabled', true ) ) {
			return;
		}

		ob_start( array( $this, 'filter_output' ) );
	}

	/**
	 * Output buffer callback.
	 *
	 * @param string $html Full page markup.
	 * @return string
	 */
	public function filter_output( $html ) {
		if ( ! is_string( $html ) || '' === $html || false === stripos( $html, '<script' ) ) {
			return $html;
		}

		// Only touch documents that look like HTML pages.
		if ( ! preg_match( '/<html[\s>]/i', $html ) ) {
			return $html;
		}

		$result = preg_replace_callback(
			'#<script\b([^>]*)>(.*?)</script>#is',
			array( $this, 'maybe_block_tag' ),
			$html
		);

		// Fail open: on regex failure (e.g. backtrack limit) keep the original page.
		return is_string( $result ) ? $result : $html;
	}

	/**
	 * Decides per <script> tag whether to neutralize it.
	 *
	 * @param array $match [full tag, attributes, body].
	 * @return string
	 */
	public function maybe_block_tag( $match ) {
		list( $tag, $attrs, $body ) = $match;

		// Already managed by us, or already inert.
		if ( false !== stripos( $attrs, 'data-pcm-' ) || preg_match( '/type\s*=\s*["\']?text\/plain/i', $attrs ) ) {
			return $tag;
		}
		// Never touch JSON/LD, importmaps, templates.
		if ( preg_match( '/type\s*=\s*["\']?(application|text)\/(ld\+json|json|template)/i', $attrs ) ) {
			return $tag;
		}

		$category = '';
		$src      = '';

		if ( preg_match( '/\bsrc\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $attrs, $m ) ) {
			$src      = ! empty( $m[2] ) ? $m[2] : ( ! empty( $m[3] ) ? $m[3] : ( $m[4] ?? '' ) );
			$category = $this->category_for_url( $src );
		} elseif ( '' !== trim( $body ) ) {
			$category = $this->category_for_inline( $body );
		}

		if ( '' === $category ) {
			return $tag;
		}

		/**
		 * Filters the blocking decision for an auto-detected script.
		 *
		 * @param string $category Category to require ('' = don't block).
		 * @param string $src      Script src ('' for inline).
		 * @param string $body     Inline body ('' for external).
		 */
		$category = apply_filters( 'pcm_autoblock_category', $category, $src, $body );
		if ( '' === $category ) {
			return $tag;
		}

		// Neutralize: force type="text/plain" and tag with the category.
		$attrs = preg_replace( '/\stype\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $attrs );
		return sprintf(
			'<script type="text/plain" data-pcm-autoblocked="1" data-pcm-category="%s"%s>%s</script>',
			esc_attr( $category ),
			$attrs,
			$body
		);
	}

	/**
	 * Maps a script URL to a consent category via the configured domain lists.
	 *
	 * @param string $url Script URL.
	 * @return string Category slug or '' when the script must not be blocked.
	 */
	public function category_for_url( $url ) {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( '' === $host ) {
			return '';
		}

		// Never block same-site scripts automatically.
		$site_host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		if ( $host === $site_host ) {
			return '';
		}

		// Allowlist wins.
		foreach ( (array) pcm_get_setting( 'blocker.allowlist', array() ) as $allowed ) {
			if ( $this->host_matches( $host, $allowed ) ) {
				return '';
			}
		}

		foreach ( (array) pcm_get_setting( 'blocker.domains', array() ) as $domain => $category ) {
			if ( $this->host_matches( $host, $domain ) ) {
				return pcm_sanitize_category_slug( $category ) ?: 'analytics';
			}
		}

		// Admin classifications from the Cookie/Script Scanner also block.
		foreach ( (array) pcm_get_setting( 'scanner.classifications', array() ) as $domain => $category ) {
			if ( in_array( $category, array( 'unknown', 'necessary' ), true ) ) {
				continue;
			}
			if ( $this->host_matches( $host, $domain ) ) {
				return pcm_sanitize_category_slug( $category ) ?: 'analytics';
			}
		}
		return '';
	}

	/**
	 * Classifies inline code by known tracking signatures.
	 *
	 * @param string $body Inline script body.
	 * @return string Category slug or ''.
	 */
	public function category_for_inline( $body ) {
		foreach ( $this->inline_signatures as $needle => $category ) {
			if ( false !== stripos( $body, $needle ) ) {
				// Respect the allowlist for inline signatures too.
				foreach ( (array) pcm_get_setting( 'blocker.allowlist', array() ) as $allowed ) {
					if ( false !== stripos( $needle, $allowed ) ) {
						return '';
					}
				}
				return $category;
			}
		}
		return '';
	}

	/**
	 * Host match supporting exact and subdomain matching
	 * ("clarity.ms" matches "www.clarity.ms").
	 *
	 * @param string $host    Actual host.
	 * @param string $pattern Configured domain (optionally "*.example.com").
	 * @return bool
	 */
	private function host_matches( $host, $pattern ) {
		$pattern = strtolower( ltrim( (string) $pattern, '*.' ) );
		if ( '' === $pattern ) {
			return false;
		}
		return $host === $pattern || str_ends_with( $host, '.' . $pattern );
	}
}
