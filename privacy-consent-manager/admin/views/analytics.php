<?php
/**
 * Analytics integrations (GA4, Consent Mode, Clarity, Cloudflare, GTM).
 *
 * @package PCM
 * @var array $settings
 */

defined( 'ABSPATH' ) || exit;

use PCM\Admin\Admin;

$pcm_categories = $settings['categories'];

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- tab routing only.
$pcm_tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'ga4';
$pcm_tabs = array(
	'ga4'        => __( 'Google Analytics', 'privacy-consent-manager' ),
	'clarity'    => __( 'Microsoft Clarity', 'privacy-consent-manager' ),
	'cloudflare' => __( 'Cloudflare Analytics', 'privacy-consent-manager' ),
	'gtm'        => __( 'Google Tag Manager', 'privacy-consent-manager' ),
);
if ( ! isset( $pcm_tabs[ $pcm_tab ] ) ) {
	$pcm_tab = 'ga4';
}

/**
 * Renders a consent-category dropdown.
 *
 * @param string $name       Field name.
 * @param string $current    Current value.
 * @param array  $categories Categories.
 */
$pcm_category_select = static function ( $name, $current, $categories ) {
	echo '<select name="' . esc_attr( $name ) . '">';
	foreach ( $categories as $slug => $category ) {
		printf(
			'<option value="%s" %s>%s</option>',
			esc_attr( $slug ),
			selected( $current, $slug, false ),
			esc_html( $category['label'] )
		);
	}
	echo '</select>';
};

Admin::maybe_notice();
?>
<h1><?php esc_html_e( 'Analytics', 'privacy-consent-manager' ); ?></h1>

<nav class="nav-tab-wrapper">
	<?php foreach ( $pcm_tabs as $pcm_key => $pcm_label ) : ?>
		<a class="nav-tab <?php echo $pcm_tab === $pcm_key ? 'nav-tab-active' : ''; ?>"
			href="<?php echo esc_url( admin_url( 'admin.php?page=pcm-analytics&tab=' . $pcm_key ) ); ?>"><?php echo esc_html( $pcm_label ); ?></a>
	<?php endforeach; ?>
</nav>

<?php if ( 'ga4' === $pcm_tab ) : ?>
	<?php Admin::form_open( array( 'ga4', 'consent_mode' ) ); ?>
	<h2><?php esc_html_e( 'Google Analytics 4', 'privacy-consent-manager' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Enable', 'privacy-consent-manager' ); ?></th>
			<td><label><input type="checkbox" name="pcm[ga4][enabled]" value="1" <?php checked( $settings['ga4']['enabled'] ); ?> /> <?php esc_html_e( 'Load GA4 (gtag.js) after consent', 'privacy-consent-manager' ); ?></label></td>
		</tr>
		<tr>
			<th scope="row"><label for="pcm-ga4-id"><?php esc_html_e( 'Measurement ID', 'privacy-consent-manager' ); ?></label></th>
			<td><input id="pcm-ga4-id" type="text" name="pcm[ga4][measurement_id]" value="<?php echo esc_attr( $settings['ga4']['measurement_id'] ); ?>" placeholder="G-XXXXXXXXXX" /></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Consent category', 'privacy-consent-manager' ); ?></th>
			<td><?php $pcm_category_select( 'pcm[ga4][category]', $settings['ga4']['category'], $pcm_categories ); ?></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'IP anonymization', 'privacy-consent-manager' ); ?></th>
			<td><label><input type="checkbox" name="pcm[ga4][anonymize_ip]" value="1" <?php checked( $settings['ga4']['anonymize_ip'] ); ?> /> <?php esc_html_e( 'Send anonymize_ip with the GA4 config', 'privacy-consent-manager' ); ?></label></td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Google Consent Mode v2', 'privacy-consent-manager' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Enable', 'privacy-consent-manager' ); ?></th>
			<td>
				<label><input type="checkbox" name="pcm[consent_mode][enabled]" value="1" <?php checked( $settings['consent_mode']['enabled'] ); ?> /> <?php esc_html_e( 'Set default denied consent signals before Google tags initialize and send updates when the visitor decides', 'privacy-consent-manager' ); ?></label>
				<p class="description"><?php esc_html_e( 'Signals: ad_storage, ad_user_data, ad_personalization, analytics_storage, functionality_storage, personalization_storage, security_storage.', 'privacy-consent-manager' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Ads data redaction', 'privacy-consent-manager' ); ?></th>
			<td><label><input type="checkbox" name="pcm[consent_mode][ads_data_redaction]" value="1" <?php checked( $settings['consent_mode']['ads_data_redaction'] ); ?> /> <?php esc_html_e( 'Redact ad click identifiers while ad_storage is denied', 'privacy-consent-manager' ); ?></label></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'URL passthrough', 'privacy-consent-manager' ); ?></th>
			<td><label><input type="checkbox" name="pcm[consent_mode][url_passthrough]" value="1" <?php checked( $settings['consent_mode']['url_passthrough'] ); ?> /> <?php esc_html_e( 'Pass ad click information through URLs while consent is denied', 'privacy-consent-manager' ); ?></label></td>
		</tr>
	</table>
	<?php Admin::form_close(); ?>

<?php elseif ( 'clarity' === $pcm_tab ) : ?>
	<?php Admin::form_open( array( 'clarity' ) ); ?>
	<h2><?php esc_html_e( 'Microsoft Clarity', 'privacy-consent-manager' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Enable', 'privacy-consent-manager' ); ?></th>
			<td><label><input type="checkbox" name="pcm[clarity][enabled]" value="1" <?php checked( $settings['clarity']['enabled'] ); ?> /> <?php esc_html_e( 'Load Microsoft Clarity after consent', 'privacy-consent-manager' ); ?></label></td>
		</tr>
		<tr>
			<th scope="row"><label for="pcm-clarity-id"><?php esc_html_e( 'Project ID', 'privacy-consent-manager' ); ?></label></th>
			<td><input id="pcm-clarity-id" type="text" name="pcm[clarity][project_id]" value="<?php echo esc_attr( $settings['clarity']['project_id'] ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Consent category', 'privacy-consent-manager' ); ?></th>
			<td><?php $pcm_category_select( 'pcm[clarity][category]', $settings['clarity']['category'], $pcm_categories ); ?></td>
		</tr>
	</table>
	<p class="description"><?php esc_html_e( 'If the official Microsoft Clarity plugin is also active, this integration refuses to initialize a second tracker and the Plugin Conflicts screen will flag the duplicate.', 'privacy-consent-manager' ); ?></p>
	<?php Admin::form_close(); ?>

<?php elseif ( 'cloudflare' === $pcm_tab ) : ?>
	<?php Admin::form_open( array( 'cloudflare' ) ); ?>
	<h2><?php esc_html_e( 'Cloudflare Web Analytics', 'privacy-consent-manager' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Enable', 'privacy-consent-manager' ); ?></th>
			<td><label><input type="checkbox" name="pcm[cloudflare][enabled]" value="1" <?php checked( $settings['cloudflare']['enabled'] ); ?> /> <?php esc_html_e( 'Load the Cloudflare Web Analytics beacon', 'privacy-consent-manager' ); ?></label></td>
		</tr>
		<tr>
			<th scope="row"><label for="pcm-cf-token"><?php esc_html_e( 'Token', 'privacy-consent-manager' ); ?></label></th>
			<td><input id="pcm-cf-token" type="text" class="regular-text" name="pcm[cloudflare][token]" value="<?php echo esc_attr( $settings['cloudflare']['token'] ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Consent requirement', 'privacy-consent-manager' ); ?></th>
			<td>
				<label><input type="checkbox" name="pcm[cloudflare][require_consent]" value="1" <?php checked( $settings['cloudflare']['require_consent'] ); ?> /> <?php esc_html_e( 'Require consent before loading the beacon', 'privacy-consent-manager' ); ?></label>
				<p class="description"><?php esc_html_e( 'Cloudflare Web Analytics is cookieless; whether it requires consent depends on your legal assessment. This applies only to the Web Analytics beacon, not other Cloudflare products.', 'privacy-consent-manager' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Consent category', 'privacy-consent-manager' ); ?></th>
			<td><?php $pcm_category_select( 'pcm[cloudflare][category]', $settings['cloudflare']['category'], $pcm_categories ); ?></td>
		</tr>
	</table>
	<p class="description"><?php esc_html_e( 'Note: if Web Analytics auto-injection is enabled on your Cloudflare dashboard, disable it there — edge-injected scripts cannot be blocked by any WordPress plugin.', 'privacy-consent-manager' ); ?></p>
	<?php Admin::form_close(); ?>

<?php else : ?>
	<?php Admin::form_open( array( 'gtm' ) ); ?>
	<h2><?php esc_html_e( 'Google Tag Manager', 'privacy-consent-manager' ); ?></h2>
	<div class="notice notice-warning inline">
		<p><?php esc_html_e( 'GTM can load additional third-party scripts. Make sure all tags inside GTM respect the site\'s consent configuration — Consent Mode signals are forwarded, but tags that ignore them will still fire.', 'privacy-consent-manager' ); ?></p>
	</div>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Enable', 'privacy-consent-manager' ); ?></th>
			<td><label><input type="checkbox" name="pcm[gtm][enabled]" value="1" <?php checked( $settings['gtm']['enabled'] ); ?> /> <?php esc_html_e( 'Load GTM after consent', 'privacy-consent-manager' ); ?></label></td>
		</tr>
		<tr>
			<th scope="row"><label for="pcm-gtm-id"><?php esc_html_e( 'Container ID', 'privacy-consent-manager' ); ?></label></th>
			<td><input id="pcm-gtm-id" type="text" name="pcm[gtm][container_id]" value="<?php echo esc_attr( $settings['gtm']['container_id'] ); ?>" placeholder="GTM-XXXXXXX" /></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Consent category', 'privacy-consent-manager' ); ?></th>
			<td><?php $pcm_category_select( 'pcm[gtm][category]', $settings['gtm']['category'], $pcm_categories ); ?></td>
		</tr>
	</table>
	<?php Admin::form_close(); ?>
<?php endif; ?>
