<?php
/**
 * General settings: consent behaviour, storage, blocker, uninstall.
 *
 * @package PCM
 * @var array $settings
 */

defined( 'ABSPATH' ) || exit;

use PCM\Admin\Admin;

Admin::maybe_notice();
?>
<h1><?php esc_html_e( 'Settings', 'privacy-consent-manager' ); ?></h1>

<?php Admin::form_open( array( 'consent', 'blocker', 'advanced' ) ); ?>

<h2><?php esc_html_e( 'Consent', 'privacy-consent-manager' ); ?></h2>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Banner enabled', 'privacy-consent-manager' ); ?></th>
		<td><label><input type="checkbox" name="pcm[consent][banner_enabled]" value="1" <?php checked( $settings['consent']['banner_enabled'] ); ?> /> <?php esc_html_e( 'Show consent banner', 'privacy-consent-manager' ); ?></label></td>
	</tr>
	<tr>
		<th scope="row"><label for="pcm-consent-version"><?php esc_html_e( 'Consent version', 'privacy-consent-manager' ); ?></label></th>
		<td>
			<input id="pcm-consent-version" type="text" name="pcm[consent][consent_version]" value="<?php echo esc_attr( $settings['consent']['consent_version'] ); ?>" />
			<p class="description"><?php esc_html_e( 'Bump this to re-prompt all visitors (e.g. after adding a new tracker).', 'privacy-consent-manager' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Re-prompt on version change', 'privacy-consent-manager' ); ?></th>
		<td><label><input type="checkbox" name="pcm[consent][reprompt_on_change]" value="1" <?php checked( $settings['consent']['reprompt_on_change'] ); ?> /> <?php esc_html_e( 'Treat stored consent from an older version as absent', 'privacy-consent-manager' ); ?></label></td>
	</tr>
	<tr>
		<th scope="row"><label for="pcm-cookie-expiry"><?php esc_html_e( 'Consent cookie lifetime (days)', 'privacy-consent-manager' ); ?></label></th>
		<td><input id="pcm-cookie-expiry" type="number" min="1" max="730" name="pcm[consent][cookie_expiry]" value="<?php echo esc_attr( $settings['consent']['cookie_expiry'] ); ?>" /></td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Server-side consent records', 'privacy-consent-manager' ); ?></th>
		<td><label><input type="checkbox" name="pcm[consent][store_records]" value="1" <?php checked( $settings['consent']['store_records'] ); ?> /> <?php esc_html_e( 'Store anonymous consent records in the database (proof of consent)', 'privacy-consent-manager' ); ?></label></td>
	</tr>
	<tr>
		<th scope="row"><label for="pcm-retention"><?php esc_html_e( 'Record retention (days)', 'privacy-consent-manager' ); ?></label></th>
		<td><input id="pcm-retention" type="number" min="30" max="3650" name="pcm[consent][retention_days]" value="<?php echo esc_attr( $settings['consent']['retention_days'] ); ?>" /></td>
	</tr>
</table>

<h2><?php esc_html_e( 'Automatic Script Blocker', 'privacy-consent-manager' ); ?></h2>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Enable', 'privacy-consent-manager' ); ?></th>
		<td>
			<label><input type="checkbox" name="pcm[blocker][enabled]" value="1" <?php checked( $settings['blocker']['enabled'] ); ?> /> <?php esc_html_e( 'Automatically hold known third-party tracking scripts until consent', 'privacy-consent-manager' ); ?></label>
			<p class="description"><?php esc_html_e( 'Applies to scripts injected by themes and other plugins. Same-site scripts are never blocked.', 'privacy-consent-manager' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="pcm-blocked-domains"><?php esc_html_e( 'Blocked domains', 'privacy-consent-manager' ); ?></label></th>
		<td>
			<textarea id="pcm-blocked-domains" class="large-text code" rows="8" name="pcm_blocker_domains"><?php
			foreach ( $settings['blocker']['domains'] as $pcm_domain => $pcm_category ) {
				echo esc_textarea( $pcm_domain . ' ' . $pcm_category . "\n" );
			}
			?></textarea>
			<p class="description"><?php esc_html_e( 'One per line: domain followed by its consent category, e.g. "clarity.ms analytics". Subdomains match automatically.', 'privacy-consent-manager' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="pcm-allowlist"><?php esc_html_e( 'Allowlist', 'privacy-consent-manager' ); ?></label></th>
		<td>
			<textarea id="pcm-allowlist" class="large-text code" rows="4" name="pcm_blocker_allowlist"><?php echo esc_textarea( implode( "\n", $settings['blocker']['allowlist'] ) ); ?></textarea>
			<p class="description"><?php esc_html_e( 'Domains that must never be auto-blocked (one per line). The allowlist always wins.', 'privacy-consent-manager' ); ?></p>
		</td>
	</tr>
</table>

<h2><?php esc_html_e( 'Advanced', 'privacy-consent-manager' ); ?></h2>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Delete plugin data on uninstall', 'privacy-consent-manager' ); ?></th>
		<td>
			<label><input type="checkbox" name="pcm[advanced][delete_on_uninstall]" value="1" <?php checked( $settings['advanced']['delete_on_uninstall'] ); ?> /> <?php esc_html_e( 'Remove all settings and consent records when the plugin is uninstalled (never on deactivation)', 'privacy-consent-manager' ); ?></label>
		</td>
	</tr>
</table>

<?php Admin::form_close(); ?>
