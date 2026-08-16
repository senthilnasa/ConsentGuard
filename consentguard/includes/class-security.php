<?php
/**
 * Cross-cutting security hardening.
 *
 * @package PCM
 */

namespace PCM;

defined( 'ABSPATH' ) || exit;

/**
 * Centralizes capability definitions and small hardening measures.
 * Input sanitization lives next to each input (Settings::sanitize,
 * Consent_Manager::sanitize_record); this class defines who may do what.
 */
class Security {

	/**
	 * Capability required to manage plugin settings.
	 */
	const CAP_MANAGE = 'manage_options';

	/**
	 * Capability required to add/edit custom scripts. Script injection is a
	 * code-execution power, so it additionally requires unfiltered_html
	 * (enforced in pcm_sanitize_script_code()).
	 */
	const CAP_SCRIPTS = 'manage_options';

	/**
	 * Registers hooks.
	 */
	public function register() {
		add_action( 'wp_ajax_pcm_dismiss_notice', array( $this, 'ajax_dismiss_notice' ) );
	}

	/**
	 * Verifies an admin action nonce or dies.
	 *
	 * @param string $action Nonce action.
	 */
	public static function verify_admin_action( $action ) {
		if ( ! current_user_can( self::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'consentguard' ), 403 );
		}
		check_admin_referer( $action, '_pcm_nonce' );
	}

	/**
	 * Persists dismissal of the legal notice per user.
	 */
	public function ajax_dismiss_notice() {
		check_ajax_referer( 'pcm_admin', 'nonce' );
		if ( ! current_user_can( self::CAP_MANAGE ) ) {
			wp_send_json_error( null, 403 );
		}
		update_user_meta( get_current_user_id(), 'pcm_legal_notice_dismissed', 1 );
		wp_send_json_success();
	}
}
