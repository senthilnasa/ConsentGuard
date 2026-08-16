<?php
/**
 * Jurisdiction rules + DPDP configuration.
 *
 * @package PCM
 * @var array $settings
 */

defined( 'ABSPATH' ) || exit;

use PCM\Admin\Admin;

$pcm_j        = $settings['jurisdictions'];
$pcm_profiles = $pcm_j['profiles'];

Admin::maybe_notice();
?>
<h1><?php esc_html_e( 'Jurisdiction Rules', 'consentguard' ); ?></h1>

<?php Admin::form_open( array( 'jurisdictions', 'dpdp' ) ); ?>

<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Geolocation', 'consentguard' ); ?></th>
		<td>
			<label><input type="hidden" name="pcm[jurisdictions][geo_enabled]" value="" /><input type="checkbox" name="pcm[jurisdictions][geo_enabled]" value="1" <?php checked( $pcm_j['geo_enabled'] ); ?> /> <?php esc_html_e( 'Resolve the visitor country from trusted infrastructure headers (Cloudflare, GeoIP modules)', 'consentguard' ); ?></label>
			<p class="description"><?php esc_html_e( 'No external geolocation API is called and no IP address is stored. When disabled — or when no header is available — every visitor gets the default profile, so keep the default profile the strictest one you need. Note: with full-page caching, per-country behaviour requires your cache to vary on the country header.', 'consentguard' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Default profile', 'consentguard' ); ?></th>
		<td>
			<select name="pcm[jurisdictions][default_profile]">
				<?php foreach ( $pcm_profiles as $pcm_key => $pcm_profile ) : ?>
					<option value="<?php echo esc_attr( $pcm_key ); ?>" <?php selected( $pcm_j['default_profile'], $pcm_key ); ?>><?php echo esc_html( $pcm_profile['label'] ); ?></option>
				<?php endforeach; ?>
			</select>
		</td>
	</tr>
</table>

<h2><?php esc_html_e( 'Country → Profile rules', 'consentguard' ); ?></h2>
<table class="widefat striped pcm-table" style="max-width:560px">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Country code (ISO 3166-1 alpha-2)', 'consentguard' ); ?></th>
			<th><?php esc_html_e( 'Profile', 'consentguard' ); ?></th>
		</tr>
	</thead>
	<tbody>
	<?php
	$pcm_rules     = $pcm_j['rules'];
	$pcm_rules[''] = ''; // Blank row for adding.
	foreach ( $pcm_rules as $pcm_country => $pcm_profile_key ) :
		?>
		<tr>
			<td><input type="text" maxlength="2" size="4" name="pcm_rule_countries[]" value="<?php echo esc_attr( $pcm_country ); ?>" placeholder="IN" /></td>
			<td>
				<select name="pcm_rule_profiles[]">
					<?php foreach ( $pcm_profiles as $pcm_key => $pcm_profile ) : ?>
						<option value="<?php echo esc_attr( $pcm_key ); ?>" <?php selected( $pcm_profile_key, $pcm_key ); ?>><?php echo esc_html( $pcm_profile['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
<p class="description"><?php esc_html_e( 'EU/EEA countries automatically map to the GDPR profile unless overridden. Clear a country code to remove its rule.', 'consentguard' ); ?></p>

<h2><?php esc_html_e( 'Profiles', 'consentguard' ); ?></h2>
<table class="widefat striped pcm-table" style="max-width:760px">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Profile', 'consentguard' ); ?></th>
			<th><?php esc_html_e( 'Consent model', 'consentguard' ); ?></th>
			<th><?php esc_html_e( 'Require consent before non-essential tracking', 'consentguard' ); ?></th>
			<th><?php esc_html_e( 'Show Reject All', 'consentguard' ); ?></th>
			<th><?php esc_html_e( 'Granular categories', 'consentguard' ); ?></th>
		</tr>
	</thead>
	<tbody>
	<?php foreach ( $pcm_profiles as $pcm_key => $pcm_profile ) : ?>
		<tr>
			<td>
				<input type="text" name="pcm[jurisdictions][profiles][<?php echo esc_attr( $pcm_key ); ?>][label]" value="<?php echo esc_attr( $pcm_profile['label'] ); ?>" />
			</td>
			<td>
				<select name="pcm[jurisdictions][profiles][<?php echo esc_attr( $pcm_key ); ?>][mode]">
					<option value="opt_in" <?php selected( $pcm_profile['mode'] ?? 'opt_in', 'opt_in' ); ?>><?php esc_html_e( 'Opt-in (consent first)', 'consentguard' ); ?></option>
					<option value="opt_out" <?php selected( $pcm_profile['mode'] ?? 'opt_in', 'opt_out' ); ?>><?php esc_html_e( 'Opt-out (implied until objection)', 'consentguard' ); ?></option>
					<option value="notice_only" <?php selected( $pcm_profile['mode'] ?? 'opt_in', 'notice_only' ); ?>><?php esc_html_e( 'Notice only', 'consentguard' ); ?></option>
				</select>
			</td>
			<td><input type="hidden" name="pcm[jurisdictions][profiles][<?php echo esc_attr( $pcm_key ); ?>][require_consent]" value="" /><input type="checkbox" name="pcm[jurisdictions][profiles][<?php echo esc_attr( $pcm_key ); ?>][require_consent]" value="1" <?php checked( ! empty( $pcm_profile['require_consent'] ) ); ?> /></td>
			<td><input type="hidden" name="pcm[jurisdictions][profiles][<?php echo esc_attr( $pcm_key ); ?>][show_reject_all]" value="" /><input type="checkbox" name="pcm[jurisdictions][profiles][<?php echo esc_attr( $pcm_key ); ?>][show_reject_all]" value="1" <?php checked( ! empty( $pcm_profile['show_reject_all'] ) ); ?> /></td>
			<td><input type="hidden" name="pcm[jurisdictions][profiles][<?php echo esc_attr( $pcm_key ); ?>][granular]" value="" /><input type="checkbox" name="pcm[jurisdictions][profiles][<?php echo esc_attr( $pcm_key ); ?>][granular]" value="1" <?php checked( ! empty( $pcm_profile['granular'] ) ); ?> /></td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>

<h2><?php esc_html_e( 'India / DPDP Configuration', 'consentguard' ); ?></h2>
<p class="description"><?php esc_html_e( 'These texts are shown to visitors matched to the DPDP profile. Nothing is hard-coded — adapt the wording to your organization\'s actual data processing and legal advice.', 'consentguard' ); ?></p>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><label for="pcm-dpdp-notice"><?php esc_html_e( 'Notice text', 'consentguard' ); ?></label></th>
		<td><textarea id="pcm-dpdp-notice" class="large-text" rows="3" name="pcm[dpdp][notice_text]"><?php echo esc_textarea( $settings['dpdp']['notice_text'] ); ?></textarea></td>
	</tr>
	<tr>
		<th scope="row"><label for="pcm-dpdp-purpose"><?php esc_html_e( 'Purpose descriptions', 'consentguard' ); ?></label></th>
		<td><textarea id="pcm-dpdp-purpose" class="large-text" rows="3" name="pcm[dpdp][purpose_text]"><?php echo esc_textarea( $settings['dpdp']['purpose_text'] ); ?></textarea></td>
	</tr>
	<tr>
		<th scope="row"><label for="pcm-dpdp-rights"><?php esc_html_e( 'User rights information', 'consentguard' ); ?></label></th>
		<td><textarea id="pcm-dpdp-rights" class="large-text" rows="3" name="pcm[dpdp][rights_text]"><?php echo esc_textarea( $settings['dpdp']['rights_text'] ); ?></textarea></td>
	</tr>
	<tr>
		<th scope="row"><label for="pcm-dpdp-contact"><?php esc_html_e( 'Contact email', 'consentguard' ); ?></label></th>
		<td><input id="pcm-dpdp-contact" type="email" class="regular-text" name="pcm[dpdp][contact_email]" value="<?php echo esc_attr( $settings['dpdp']['contact_email'] ); ?>" /></td>
	</tr>
	<tr>
		<th scope="row"><label for="pcm-dpdp-grievance"><?php esc_html_e( 'Grievance / contact information', 'consentguard' ); ?></label></th>
		<td><textarea id="pcm-dpdp-grievance" class="large-text" rows="3" name="pcm[dpdp][grievance_info]"><?php echo esc_textarea( $settings['dpdp']['grievance_info'] ); ?></textarea></td>
	</tr>
</table>

<?php Admin::form_close(); ?>
