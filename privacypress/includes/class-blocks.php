<?php
/**
 * Gutenberg blocks.
 *
 * @package PCM
 */

namespace PCM;

defined( 'ABSPATH' ) || exit;

/**
 * Registers two dynamic blocks:
 *
 * - privacypress/privacy-settings: a button/link that opens the consent
 *   preferences modal (same behaviour as the [pcm_privacy_settings]
 *   shortcode).
 * - privacypress/cookie-table: renders the live cookie inventory as a
 *   table — ideal for the Cookie Policy page, always in sync with the
 *   configuration.
 */
class Blocks {

	/**
	 * Registers hooks.
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_blocks' ) );
	}

	/**
	 * Registers the block types + editor script.
	 */
	public function register_blocks() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		wp_register_script(
			'pcm-blocks',
			PCM_PLUGIN_URL . 'admin/js/blocks.js',
			array( 'wp-blocks', 'wp-element', 'wp-i18n' ),
			PCM_VERSION,
			true
		);

		register_block_type(
			'privacypress/privacy-settings',
			array(
				'editor_script'   => 'pcm-blocks',
				'render_callback' => array( $this, 'render_privacy_settings' ),
				'attributes'      => array(
					'label' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);

		register_block_type(
			'privacypress/cookie-table',
			array(
				'editor_script'   => 'pcm-blocks',
				'render_callback' => array( $this, 'render_cookie_table' ),
			)
		);
	}

	/**
	 * Renders the privacy-settings button block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render_privacy_settings( $attributes ) {
		$label = ! empty( $attributes['label'] )
			? sanitize_text_field( $attributes['label'] )
			: pcm_get_setting( 'banner.reopen_label', __( 'Privacy Settings', 'privacypress' ) );

		return sprintf(
			'<button type="button" class="pcm-open-preferences pcm-inline-open">%s</button>',
			esc_html( $label )
		);
	}

	/**
	 * Renders the live cookie inventory as a table.
	 *
	 * @return string
	 */
	public function render_cookie_table() {
		$categories = pcm_get_setting( 'categories', array() );
		$inventory  = (array) pcm_get_setting( 'cookies', array() );

		$out = '<div class="pcm-cookie-table-block">';
		foreach ( $categories as $slug => $category ) {
			$rows = (array) ( $inventory[ $slug ] ?? array() );
			if ( empty( $rows ) ) {
				continue;
			}
			$out .= '<h3>' . esc_html( $category['label'] ) . '</h3>';
			$out .= '<table><thead><tr><th>' . esc_html__( 'Cookie', 'privacypress' ) . '</th><th>' . esc_html__( 'Duration', 'privacypress' ) . '</th><th>' . esc_html__( 'Description', 'privacypress' ) . '</th></tr></thead><tbody>';
			foreach ( $rows as $cookie ) {
				$out .= sprintf(
					'<tr><td><code>%s</code></td><td>%s</td><td>%s</td></tr>',
					esc_html( $cookie['name'] ?? '' ),
					esc_html( $cookie['duration'] ?? '' ),
					esc_html( $cookie['description'] ?? '' )
				);
			}
			$out .= '</tbody></table>';
		}
		$out .= '</div>';

		return $out;
	}
}
