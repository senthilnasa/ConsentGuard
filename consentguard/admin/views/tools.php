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
<h1><?php esc_html_e( 'Tools', 'consentguard' ); ?></h1>

<?php Admin::form_open( array( 'advanced' ) ); ?>

<h2><?php esc_html_e( 'Debug Mode', 'consentguard' ); ?></h2>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Debug mode', 'consentguard' ); ?></th>
		<td>
			<label><input type="hidden" name="pcm[advanced][debug]" value="" /><input type="checkbox" name="pcm[advanced][debug]" value="1" <?php checked( $settings['advanced']['debug'] ); ?> /> <?php esc_html_e( 'Log consent decisions and script blocking to the browser console ([PCM] prefix)', 'consentguard' ); ?></label>
			<p class="description"><?php esc_html_e( 'For development only — disable on production sites. No sensitive information is logged either way.', 'consentguard' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Keep uninstall data setting', 'consentguard' ); ?></th>
		<td>
			<label><input type="hidden" name="pcm[advanced][delete_on_uninstall]" value="" /><input type="checkbox" name="pcm[advanced][delete_on_uninstall]" value="1" <?php checked( $settings['advanced']['delete_on_uninstall'] ); ?> /> <?php esc_html_e( 'Delete plugin data on uninstall', 'consentguard' ); ?></label>
		</td>
	</tr>
</table>

<?php Admin::form_close(); ?>

<h2><?php esc_html_e( 'Import / Export Settings', 'consentguard' ); ?></h2>
<p>
	<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=pcm_export_settings' ), 'pcm_export_settings', '_pcm_nonce' ) ); ?>">
		<?php esc_html_e( 'Export settings (JSON)', 'consentguard' ); ?>
	</a>
</p>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'pcm_import_settings', '_pcm_nonce' ); ?>
	<input type="hidden" name="action" value="pcm_import_settings" />
	<textarea class="large-text code" rows="6" name="pcm_import_json" placeholder='{"banner": {...}, "ga4": {...}}'></textarea>
	<p>
		<button type="submit" class="button button-secondary"><?php esc_html_e( 'Import settings', 'consentguard' ); ?></button>
		<span class="description"><?php esc_html_e( 'Paste an exported JSON. Every value is sanitized on import.', 'consentguard' ); ?></span>
	</p>
</form>

<h2><?php esc_html_e( 'WP-CLI', 'consentguard' ); ?></h2>
<pre class="pcm-code">wp consentguard stats
wp consentguard scan
wp consentguard cleanup
wp consentguard export --file=consents.csv</pre>

<h2><?php esc_html_e( 'Maintenance', 'consentguard' ); ?></h2>
<p>
	<?php
	$pcm_next = wp_next_scheduled( 'pcm_cleanup_consents' );
	if ( $pcm_next ) {
		printf(
			/* translators: %s: human-readable time difference */
			esc_html__( 'Next automatic consent-record cleanup runs in %s.', 'consentguard' ),
			esc_html( human_time_diff( $pcm_next ) )
		);
	} else {
		esc_html_e( 'Cleanup task is not scheduled. Deactivate and reactivate the plugin to reschedule it.', 'consentguard' );
	}
	?>
</p>

<h2><?php esc_html_e( 'Consent API quick reference', 'consentguard' ); ?></h2>
<pre class="pcm-code">PrivacyConsent.getConsent();
PrivacyConsent.hasConsent('analytics');
PrivacyConsent.onChange(function (consent) { /* ... */ });
PrivacyConsent.openPreferences();

document.addEventListener('privacy_consent_changed', function (e) {
	console.log(e.detail);
});</pre>
<p class="description"><?php esc_html_e( 'See the developer documentation for all hooks, filters and events.', 'consentguard' ); ?></p>
