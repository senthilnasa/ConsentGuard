<?php
/**
 * Elementor widget class. Loaded only from integrations/elementor.php once
 * Elementor is confirmed active, so \Elementor\Widget_Base exists.
 *
 * @package PCM
 */

defined( 'ABSPATH' ) || exit;

/**
 * "Privacy Settings Button" Elementor widget — renders the same markup as
 * the [pcm_privacy_settings] shortcode / Gutenberg block.
 */
class PCM_Elementor_Privacy_Settings extends \Elementor\Widget_Base {

	/**
	 * Widget slug.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'pcm-privacy-settings';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Privacy Settings Button', 'consentguard' );
	}

	/**
	 * Widget icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-lock-user';
	}

	/**
	 * Widget categories.
	 *
	 * @return string[]
	 */
	public function get_categories() {
		return array( 'general' );
	}

	/**
	 * Registers the widget controls.
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'pcm_section',
			array( 'label' => __( 'Privacy Settings', 'consentguard' ) )
		);
		$this->add_control(
			'label',
			array(
				'label'       => __( 'Button label', 'consentguard' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => pcm_get_setting( 'banner.reopen_label', __( 'Privacy Settings', 'consentguard' ) ),
			)
		);
		$this->end_controls_section();
	}

	/**
	 * Renders the widget output.
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();
		$label    = ! empty( $settings['label'] )
			? sanitize_text_field( $settings['label'] )
			: pcm_get_setting( 'banner.reopen_label', __( 'Privacy Settings', 'consentguard' ) );
		printf(
			'<button type="button" class="pcm-open-preferences pcm-inline-open">%s</button>',
			esc_html( $label )
		);
	}
}
