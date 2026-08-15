<?php
/**
 * Deactivation routine.
 *
 * @package PCM
 */

namespace PCM;

defined( 'ABSPATH' ) || exit;

/**
 * Clears scheduled events. Never deletes data on deactivation.
 */
class Deactivator {

	/**
	 * Runs on plugin deactivation.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'pcm_cleanup_consents' );
	}
}
