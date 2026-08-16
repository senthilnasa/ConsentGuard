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
<h1><?php esc_html_e( 'Settings', 'consentguard' ); ?></h1>

<?php Admin::form_open( array( 'consent', 'blocker', 'advanced' ) ); ?>

<h2><?php esc_html_e( 'Consent', 'consentguard' ); ?></h2>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Banner enabled', 'consentguard' ); ?></th>
		<td><label><input type="hidden" name="pcm[consent][banner_enabled]" value="" /><input type="checkbox" name="pcm[consent][banner_enabled]" value="1" <?php checked( $settings['consent']['banner_enabled'] ); ?> /> <?php esc_html_e( 'Show consent banner', 'consentguard' ); ?></label></td>
	</tr>
	<tr>
		<th scope="row"><label for="pcm-consent-version"><?php esc_html_e( 'Consent version', 'consentguard' ); ?></label></th>
		<td>
			<input id="pcm-consent-version" type="text" name="pcm[consent][consent_version]" value="<?php echo esc_attr( $settings['consent']['consent_version'] ); ?>" />
			<p class="description"><?php esc_html_e( 'Bump this to re-prompt all visitors (e.g. after adding a new tracker).', 'consentguard' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Re-prompt on version change', 'consentguard' ); ?></th>
		<td><label><input type="hidden" name="pcm[consent][reprompt_on_change]" value="" /><input type="checkbox" name="pcm[consent][reprompt_on_change]" value="1" <?php checked( $settings['consent']['reprompt_on_change'] ); ?> /> <?php esc_html_e( 'Treat stored consent from an older version as absent', 'consentguard' ); ?></label></td>
	</tr>
	<tr>
		<th scope="row"><label for="pcm-cookie-expiry"><?php esc_html_e( 'Consent cookie lifetime (days)', 'consentguard' ); ?></label></th>
		<td><input id="pcm-cookie-expiry" type="number" min="1" max="730" name="pcm[consent][cookie_expiry]" value="<?php echo esc_attr( $settings['consent']['cookie_expiry'] ); ?>" /></td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Server-side consent records', 'consentguard' ); ?></th>
		<td><label><input type="hidden" name="pcm[consent][store_records]" value="" /><input type="checkbox" name="pcm[consent][store_records]" value="1" <?php checked( $settings['consent']['store_records'] ); ?> /> <?php esc_html_e( 'Store anonymous consent records in the database (proof of consent)', 'consentguard' ); ?></label></td>
	</tr>
	<tr>
		<th scope="row"><label for="pcm-retention"><?php esc_html_e( 'Record retention (days)', 'consentguard' ); ?></label></th>
		<td><input id="pcm-retention" type="number" min="30" max="3650" name="pcm[consent][retention_days]" value="<?php echo esc_attr( $settings['consent']['retention_days'] ); ?>" /></td>
	</tr>
</table>

<h2><?php esc_html_e( 'Automatic Script Blocker', 'consentguard' ); ?></h2>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Enable', 'consentguard' ); ?></th>
		<td>
			<label><input type="hidden" name="pcm[blocker][enabled]" value="" /><input type="checkbox" name="pcm[blocker][enabled]" value="1" <?php checked( $settings['blocker']['enabled'] ); ?> /> <?php esc_html_e( 'Automatically hold known third-party tracking scripts until consent', 'consentguard' ); ?></label>
			<p class="description"><?php esc_html_e( 'Applies to scripts injected by themes and other plugins. Same-site scripts are never blocked.', 'consentguard' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="pcm-blocked-domains"><?php esc_html_e( 'Blocked domains', 'consentguard' ); ?></label></th>
		<td>
			<textarea id="pcm-blocked-domains" class="large-text code" rows="8" name="pcm_blocker_domains"><?php
			foreach ( $settings['blocker']['domains'] as $pcm_domain => $pcm_category ) {
				echo esc_textarea( $pcm_domain . ' ' . $pcm_category . "\n" );
			}
			?></textarea>
			<p class="description"><?php esc_html_e( 'One per line: domain followed by its consent category, e.g. "clarity.ms analytics". Subdomains match automatically.', 'consentguard' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="pcm-iframe-domains"><?php esc_html_e( 'Blocked embed/iframe domains', 'consentguard' ); ?></label></th>
		<td>
			<textarea id="pcm-iframe-domains" class="large-text code" rows="6" name="pcm_blocker_iframe_domains"><?php
			foreach ( (array) ( $settings['blocker']['iframe_domains'] ?? array() ) as $pcm_domain => $pcm_category ) {
				echo esc_textarea( $pcm_domain . ' ' . $pcm_category . "\n" );
			}
			?></textarea>
			<p class="description"><?php esc_html_e( 'Embeds (YouTube, Vimeo, Spotify, Facebook, …) are replaced with an "Accept & load" placeholder until the visitor grants the category. One per line: domain followed by category. Hosts like www.google.com are not listed by default because that would also block reCAPTCHA.', 'consentguard' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="pcm-allowlist"><?php esc_html_e( 'Allowlist', 'consentguard' ); ?></label></th>
		<td>
			<textarea id="pcm-allowlist" class="large-text code" rows="4" name="pcm_blocker_allowlist"><?php echo esc_textarea( implode( "\n", $settings['blocker']['allowlist'] ) ); ?></textarea>
			<p class="description"><?php esc_html_e( 'Domains that must never be auto-blocked (one per line). The allowlist always wins.', 'consentguard' ); ?></p>
		</td>
	</tr>
</table>

<h2><?php esc_html_e( 'Translations', 'consentguard' ); ?></h2>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><label for="pcm-translations"><?php esc_html_e( 'Per-locale text overrides (JSON)', 'consentguard' ); ?></label></th>
		<td>
			<textarea id="pcm-translations" class="large-text code" rows="8" name="pcm_translations_json" placeholder='{"ta_IN": {"banner": {"title": "...", "message": "..."}, "categories": {"analytics": {"label": "...", "description": "..."}}}}'><?php
			$pcm_translations = (array) ( $settings['translations'] ?? array() );
			echo esc_textarea( $pcm_translations ? wp_json_encode( $pcm_translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) : '' );
			?></textarea>
			<p class="description"><?php esc_html_e( 'Overrides the banner/modal texts and category labels for specific locales (e.g. ta_IN, hi_IN). Applied per request, so WPML/Polylang language switching is respected. Built-in plugin strings translate via standard .po files; WPML/Polylang users can alternatively use String Translation (a wpml-config.xml ships with the plugin).', 'consentguard' ); ?></p>
		</td>
	</tr>
</table>

<h2><?php esc_html_e( 'Advanced', 'consentguard' ); ?></h2>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Global Privacy Control', 'consentguard' ); ?></th>
		<td>
			<label><input type="hidden" name="pcm[advanced][respect_gpc]" value="" /><input type="checkbox" name="pcm[advanced][respect_gpc]" value="1" <?php checked( $settings['advanced']['respect_gpc'] ); ?> /> <?php esc_html_e( 'Respect the browser GPC signal: keep the Marketing category denied on blanket accepts (an explicit toggle in the preferences modal still wins)', 'consentguard' ); ?></label>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Delete plugin data on uninstall', 'consentguard' ); ?></th>
		<td>
			<label><input type="hidden" name="pcm[advanced][delete_on_uninstall]" value="" /><input type="checkbox" name="pcm[advanced][delete_on_uninstall]" value="1" <?php checked( $settings['advanced']['delete_on_uninstall'] ); ?> /> <?php esc_html_e( 'Remove all settings and consent records when the plugin is uninstalled (never on deactivation)', 'consentguard' ); ?></label>
		</td>
	</tr>
</table>

<?php Admin::form_close(); ?>
