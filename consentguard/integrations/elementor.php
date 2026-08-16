<?php
/**
 * Elementor integration loader.
 *
 * Registers the "Privacy Settings Button" widget when Elementor is active.
 * The widget class lives in its own file because it extends
 * \Elementor\Widget_Base, which only exists once Elementor has loaded.
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
		require_once __DIR__ . '/class-pcm-elementor-privacy-settings.php';
		$widgets_manager->register( new PCM_Elementor_Privacy_Settings() );
	}
);
