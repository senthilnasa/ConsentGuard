<?php
/**
 * Tools: debug mode, maintenance.
 *
 * @package PCM
 * @var array               $settings
 * @var PCM\Consent_Storage $storage
 */

defined( 'ABSPATH' ) || exit;

use PCM\Admin\Admin;

Admin::maybe_notice();
?>
<h1><?php esc_html_e( 'Tools', 'privacypress' ); ?></h1>

<?php Admin::form_open( array( 'advanced' ) ); ?>

<h2><?php esc_html_e( 'Debug Mode', 'privacypress' ); ?></h2>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Debug mode', 'privacypress' ); ?></th>
		<td>
			<label><input type="hidden" name="pcm[advanced][debug]" value="" /><input type="checkbox" name="pcm[advanced][debug]" value="1" <?php checked( $settings['advanced']['debug'] ); ?> /> <?php esc_html_e( 'Log consent decisions and script blocking to the browser console ([PCM] prefix)', 'privacypress' ); ?></label>
			<p class="description"><?php esc_html_e( 'For development only — disable on production sites. No sensitive information is logged either way.', 'privacypress' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Keep uninstall data setting', 'privacypress' ); ?></th>
		<td>
			<label><input type="hidden" name="pcm[advanced][delete_on_uninstall]" value="" /><input type="checkbox" name="pcm[advanced][delete_on_uninstall]" value="1" <?php checked( $settings['advanced']['delete_on_uninstall'] ); ?> /> <?php esc_html_e( 'Delete plugin data on uninstall', 'privacypress' ); ?></label>
		</td>
	</tr>
</table>

<?php Admin::form_close(); ?>

<h2><?php esc_html_e( 'Maintenance', 'privacypress' ); ?></h2>
<p>
	<?php
	$pcm_next = wp_next_scheduled( 'pcm_cleanup_consents' );
	if ( $pcm_next ) {
		printf(
			/* translators: %s: human-readable time difference */
			esc_html__( 'Next automatic consent-record cleanup runs in %s.', 'privacypress' ),
			esc_html( human_time_diff( $pcm_next ) )
		);
	} else {
		esc_html_e( 'Cleanup task is not scheduled. Deactivate and reactivate the plugin to reschedule it.', 'privacypress' );
	}
	?>
</p>

<h2><?php esc_html_e( 'Consent API quick reference', 'privacypress' ); ?></h2>
<pre class="pcm-code">PrivacyConsent.getConsent();
PrivacyConsent.hasConsent('analytics');
PrivacyConsent.onChange(function (consent) { /* ... */ });
PrivacyConsent.openPreferences();

document.addEventListener('privacy_consent_changed', function (e) {
    console.log(e.detail);
});</pre>
<p class="description"><?php esc_html_e( 'See the developer documentation for all hooks, filters and events.', 'privacypress' ); ?></p>
