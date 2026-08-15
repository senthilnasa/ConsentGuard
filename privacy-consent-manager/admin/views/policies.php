<?php
/**
 * Privacy policy pages + cookie policy generator.
 *
 * @package PCM
 * @var array $settings
 */

defined( 'ABSPATH' ) || exit;

use PCM\Admin\Admin;

Admin::maybe_notice();
?>
<h1><?php esc_html_e( 'Privacy Policies', 'privacy-consent-manager' ); ?></h1>

<?php Admin::form_open( array( 'policies' ) ); ?>

<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><label for="pcm-privacy-page"><?php esc_html_e( 'Privacy Policy page', 'privacy-consent-manager' ); ?></label></th>
		<td>
			<?php
			wp_dropdown_pages(
				array(
					'id'                => 'pcm-privacy-page',
					'name'              => 'pcm[policies][privacy_page_id]',
					'selected'          => (int) $settings['policies']['privacy_page_id'],
					'show_option_none'  => __( '— Select —', 'privacy-consent-manager' ),
					'option_none_value' => '0',
				)
			);
			?>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="pcm-cookie-page"><?php esc_html_e( 'Cookie Policy page', 'privacy-consent-manager' ); ?></label></th>
		<td>
			<?php
			wp_dropdown_pages(
				array(
					'id'                => 'pcm-cookie-page',
					'name'              => 'pcm[policies][cookie_page_id]',
					'selected'          => (int) $settings['policies']['cookie_page_id'],
					'show_option_none'  => __( '— Select —', 'privacy-consent-manager' ),
					'option_none_value' => '0',
				)
			);
			?>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="pcm-policy-version"><?php esc_html_e( 'Policy version', 'privacy-consent-manager' ); ?></label></th>
		<td>
			<input id="pcm-policy-version" type="text" name="pcm[policies][policy_version]" value="<?php echo esc_attr( $settings['policies']['policy_version'] ); ?>" />
			<p class="description"><?php esc_html_e( 'Bump this when your policies change; it is stored with every consent record.', 'privacy-consent-manager' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="pcm-generated-policy"><?php esc_html_e( 'Generated cookie policy draft', 'privacy-consent-manager' ); ?></label></th>
		<td>
			<textarea id="pcm-generated-policy" class="large-text code" rows="14" name="pcm[policies][generated_cookie_policy]"><?php echo esc_textarea( $settings['policies']['generated_cookie_policy'] ); ?></textarea>
			<p class="description"><strong><?php esc_html_e( 'Disclaimer:', 'privacy-consent-manager' ); ?></strong> <?php esc_html_e( 'Generated text is a technical starting point only and must be reviewed by your legal/privacy team before publishing. Copy it into your Cookie Policy page when ready.', 'privacy-consent-manager' ); ?></p>
		</td>
	</tr>
</table>

<?php Admin::form_close(); ?>

<p>
	<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=pcm_generate_policy' ), 'pcm_generate_policy', '_pcm_nonce' ) ); ?>">
		<?php esc_html_e( 'Generate draft from current configuration', 'privacy-consent-manager' ); ?>
	</a>
</p>
