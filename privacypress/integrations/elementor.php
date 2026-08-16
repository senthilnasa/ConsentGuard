<?php
/**
 * Elementor widget: Privacy Settings button.
 *
 * Registered only when Elementor is active. Renders the same markup as
 * the [pcm_privacy_settings] shortcode / Gutenberg block.
 *
 * @package PCM
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'elementor/widgets/register',
	static function ( $widgets_manager ) {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}

		// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound -- conditional class, Elementor-only.
		final class PCM_Elementor_Privacy_Settings extends \Elementor\Widget_Base {

			public function get_name() {
				return 'pcm-privacy-settings';
			}

			public function get_title() {
				return __( 'Privacy Settings Button', 'privacypress' );
			}

			public function get_icon() {
				return 'eicon-lock-user';
			}

			public function get_categories() {
				return array( 'general' );
			}

			protected function register_controls() {
				$this->start_controls_section(
					'pcm_section',
					array( 'label' => __( 'Privacy Settings', 'privacypress' ) )
				);
				$this->add_control(
					'label',
					array(
						'label'       => __( 'Button label', 'privacypress' ),
						'type'        => \Elementor\Controls_Manager::TEXT,
						'default'     => '',
						'placeholder' => pcm_get_setting( 'banner.reopen_label', __( 'Privacy Settings', 'privacypress' ) ),
					)
				);
				$this->end_controls_section();
			}

			protected function render() {
				$settings = $this->get_settings_for_display();
				$label    = ! empty( $settings['label'] )
					? sanitize_text_field( $settings['label'] )
					: pcm_get_setting( 'banner.reopen_label', __( 'Privacy Settings', 'privacypress' ) );
				printf(
					'<button type="button" class="pcm-open-preferences pcm-inline-open">%s</button>',
					esc_html( $label )
				);
			}
		}

		$widgets_manager->register( new PCM_Elementor_Privacy_Settings() );
	}
);
