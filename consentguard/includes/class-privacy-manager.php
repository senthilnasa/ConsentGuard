<?php
/**
 * WordPress privacy tooling integration + legal notice.
 *
 * @package PCM
 */

namespace PCM;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the plugin with WordPress' native privacy tools (exporter and
 * eraser for the anonymous consent records) and prints the mandatory
 * "not legal advice" admin notice.
 */
class Privacy_Manager {

	/**
	 * Storage.
	 *
	 * @var Consent_Storage
	 */
	private $storage;

	/**
	 * Constructor.
	 *
	 * @param Consent_Storage $storage Storage.
	 */
	public function __construct( Consent_Storage $storage ) {
		$this->storage = $storage;
	}

	/**
	 * Registers hooks.
	 */
	public function register() {
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
		add_action( 'admin_init', array( $this, 'add_privacy_policy_content' ) );
		add_action( 'admin_notices', array( $this, 'legal_notice' ) );
	}

	/**
	 * Registers a consent-record eraser keyed by anonymous ID.
	 *
	 * @param array $erasers Erasers.
	 * @return array
	 */
	public function register_eraser( $erasers ) {
		$erasers['pcm-consents'] = array(
			'eraser_friendly_name' => __( 'Consent records (ConsentGuard)', 'consentguard' ),
			'callback'             => array( $this, 'erase_consents' ),
		);
		return $erasers;
	}

	/**
	 * Eraser callback. Consent records carry no email; erasure is only
	 * possible when the requester provides their anonymous consent ID
	 * (shown in the preferences modal). Site admins can also purge by ID
	 * from the Consent Records screen.
	 *
	 * @param string $identifier Anonymous consent ID (UUID) supplied by the requester.
	 * @return array
	 */
	public function erase_consents( $identifier ) {
		$removed = 0;
		if ( wp_is_uuid( $identifier ) ) {
			$removed = $this->storage->delete_by_anonymous_id( $identifier );
		}
		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => false,
			'messages'       => $removed > 0
				? array( __( 'Consent records associated with the provided consent ID were deleted.', 'consentguard' ) )
				: array( __( 'No consent records matched the provided identifier. Note: consent records are stored under an anonymous ID, not an email address.', 'consentguard' ) ),
			'done'           => true,
		);
	}

	/**
	 * Suggests privacy policy text via the WordPress privacy policy guide.
	 */
	public function add_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$content = '<p>' . esc_html__( 'This website uses ConsentGuard to obtain and store visitor consent for cookies and similar technologies. When a visitor makes a consent choice, an anonymous record (random identifier, timestamp, chosen categories, consent version, region code and language) is stored. No IP addresses are stored by this plugin.', 'consentguard' ) . '</p>'
			. '<p>' . esc_html__( 'Consent records are retained for the configured retention period and then deleted automatically.', 'consentguard' ) . '</p>';
		wp_add_privacy_policy_content( __( 'ConsentGuard', 'consentguard' ), wp_kses_post( $content ) );
	}

	/**
	 * Prints the dismissible legal notice on plugin screens.
	 */
	public function legal_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || false === strpos( (string) $screen->id, 'pcm' ) ) {
			return;
		}
		if ( get_user_meta( get_current_user_id(), 'pcm_legal_notice_dismissed', true ) ) {
			return;
		}
		?>
		<div class="notice notice-info is-dismissible" data-pcm-notice="legal">
			<p><strong><?php esc_html_e( 'Legal Notice', 'consentguard' ); ?></strong></p>
			<p><?php esc_html_e( 'This plugin provides technical consent-management features but does not constitute legal advice or guarantee compliance with GDPR, ePrivacy, DPDP Act or other laws. Website owners are responsible for configuring the plugin according to their actual data-processing activities and applicable laws.', 'consentguard' ); ?></p>
		</div>
		<?php
	}
}
