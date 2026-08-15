<?php
/**
 * Administrator-defined custom scripts.
 *
 * @package PCM
 */

namespace PCM;

defined( 'ABSPATH' ) || exit;

/**
 * Prints admin-configured scripts (e.g. Meta Pixel) in consent-blocked form
 * at the configured position. Scripts are stored only after capability-aware
 * sanitization (see pcm_sanitize_script_code()).
 */
class Custom_Script_Manager {

	/**
	 * Registers output hooks.
	 */
	public function register() {
		if ( is_admin() ) {
			return;
		}
		add_action( 'wp_head', array( $this, 'print_position_header' ), 30 );
		add_action( 'wp_body_open', array( $this, 'print_position_body' ), 10 );
		add_action( 'wp_footer', array( $this, 'print_position_footer' ), 30 );
	}

	/**
	 * Returns enabled custom scripts.
	 *
	 * @return array[]
	 */
	public function get_scripts() {
		$scripts = (array) pcm_get_setting( 'custom_scripts', array() );

		/**
		 * Filters the custom scripts before output.
		 *
		 * @param array[] $scripts Script definitions.
		 */
		$scripts = apply_filters( 'pcm_custom_scripts', $scripts );

		return array_filter(
			$scripts,
			static function ( $script ) {
				return ! empty( $script['enabled'] ) && ! empty( $script['code'] );
			}
		);
	}

	/**
	 * Prints scripts for the header position.
	 */
	public function print_position_header() {
		$this->print_position( 'header' );
	}

	/**
	 * Prints scripts for the body position.
	 */
	public function print_position_body() {
		$this->print_position( 'body' );
	}

	/**
	 * Prints scripts for the footer position.
	 */
	public function print_position_footer() {
		$this->print_position( 'footer' );
	}

	/**
	 * Prints all enabled scripts registered for a position.
	 *
	 * @param string $position header|body|footer.
	 */
	private function print_position( $position ) {
		if ( pcm_is_elementor_editor() ) {
			return;
		}
		$gate = pcm()->module( 'script_manager' );

		foreach ( $this->get_scripts() as $script ) {
			if ( ( $script['position'] ?? 'footer' ) !== $position ) {
				continue;
			}
			$id       = 'custom-' . ( $script['id'] ?? 'script' );
			$category = $script['category'] ?? 'marketing';

			if ( ! $gate->should_output( true, $id, $category ) ) {
				continue;
			}

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with escaped attributes below.
			echo $this->render_blocked( $script['code'], $id, $category );
		}
	}

	/**
	 * Converts arbitrary admin-provided markup into blocked form.
	 *
	 * <script src> and inline <script> tags become type="text/plain"
	 * templates; any surrounding markup (noscript, img pixels) is wrapped in
	 * a hidden template element that is only attached after consent.
	 *
	 * @param string $code     Admin-provided markup.
	 * @param string $id       Script identifier.
	 * @param string $category Consent category.
	 * @return string
	 */
	public function render_blocked( $code, $id, $category ) {
		$has_script_tag = false !== stripos( $code, '<script' );

		if ( ! $has_script_tag ) {
			// Raw JS without tags: treat as inline body.
			return Script_Manager::blocked_inline( $code, $id, $category );
		}

		// Neutralize every script tag inside the snippet.
		$blocked = preg_replace_callback(
			'#<script\b([^>]*)>(.*?)</script>#is',
			static function ( $m ) use ( $category ) {
				$attrs = preg_replace( '/\stype\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $m[1] );
				return sprintf(
					'<script type="text/plain" data-pcm-managed="1" data-pcm-category="%s"%s>%s</script>',
					esc_attr( $category ),
					$attrs,
					$m[2]
				);
			},
			$code
		);

		if ( ! is_string( $blocked ) ) {
			$blocked = Script_Manager::blocked_inline( '/* invalid script skipped */', $id, $category );
		}

		return sprintf(
			"<!-- PCM custom script: %s -->\n%s\n",
			esc_html( $id ),
			$blocked
		);
	}
}
