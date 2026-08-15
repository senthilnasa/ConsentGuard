<?php
/**
 * Consent banner design settings.
 *
 * @package PCM
 * @var array $settings
 */

defined( 'ABSPATH' ) || exit;

use PCM\Admin\Admin;

$pcm_banner = $settings['banner'];

Admin::maybe_notice();
?>
<h1><?php esc_html_e( 'Consent Banner', 'privacypress' ); ?></h1>

<?php Admin::form_open( array( 'banner', 'consent' ) ); ?>

<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Enable banner', 'privacypress' ); ?></th>
		<td><label><input type="hidden" name="pcm[consent][banner_enabled]" value="" /><input type="checkbox" name="pcm[consent][banner_enabled]" value="1" <?php checked( $settings['consent']['banner_enabled'] ); ?> /> <?php esc_html_e( 'Show the consent banner to visitors without a stored decision', 'privacypress' ); ?></label></td>
	</tr>
	<tr>
		<th scope="row"><label for="pcm-banner-title"><?php esc_html_e( 'Title', 'privacypress' ); ?></label></th>
		<td><input id="pcm-banner-title" type="text" class="regular-text" name="pcm[banner][title]" value="<?php echo esc_attr( $pcm_banner['title'] ); ?>" /></td>
	</tr>
	<tr>
		<th scope="row"><label for="pcm-banner-message"><?php esc_html_e( 'Message', 'privacypress' ); ?></label></th>
		<td><textarea id="pcm-banner-message" class="large-text" rows="3" name="pcm[banner][message]"><?php echo esc_textarea( $pcm_banner['message'] ); ?></textarea></td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Position', 'privacypress' ); ?></th>
		<td>
			<select name="pcm[banner][position]">
				<?php
				$pcm_positions = array(
					'bottom'       => __( 'Bottom bar', 'privacypress' ),
					'top'          => __( 'Top bar', 'privacypress' ),
					'bottom-left'  => __( 'Bottom left box', 'privacypress' ),
					'bottom-right' => __( 'Bottom right box', 'privacypress' ),
					'center'       => __( 'Center modal', 'privacypress' ),
				);
				foreach ( $pcm_positions as $pcm_value => $pcm_label ) :
					?>
					<option value="<?php echo esc_attr( $pcm_value ); ?>" <?php selected( $pcm_banner['position'], $pcm_value ); ?>><?php echo esc_html( $pcm_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Layout', 'privacypress' ); ?></th>
		<td>
			<select name="pcm[banner][layout]">
				<option value="bar" <?php selected( $pcm_banner['layout'], 'bar' ); ?>><?php esc_html_e( 'Bar', 'privacypress' ); ?></option>
				<option value="box" <?php selected( $pcm_banner['layout'], 'box' ); ?>><?php esc_html_e( 'Box', 'privacypress' ); ?></option>
			</select>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Theme', 'privacypress' ); ?></th>
		<td>
			<select name="pcm[banner][theme]">
				<option value="light" <?php selected( $pcm_banner['theme'] ?? 'light', 'light' ); ?>><?php esc_html_e( 'Light', 'privacypress' ); ?></option>
				<option value="dark" <?php selected( $pcm_banner['theme'] ?? 'light', 'dark' ); ?>><?php esc_html_e( 'Dark', 'privacypress' ); ?></option>
				<option value="auto" <?php selected( $pcm_banner['theme'] ?? 'light', 'auto' ); ?>><?php esc_html_e( 'Auto (follow visitor system preference)', 'privacypress' ); ?></option>
			</select>
			<p class="description"><?php esc_html_e( 'Dark and Auto themes ignore the background/text color settings and use a polished dark palette; the primary color is kept.', 'privacypress' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Animation', 'privacypress' ); ?></th>
		<td>
			<select name="pcm[banner][animation]">
				<option value="slide" <?php selected( $pcm_banner['animation'], 'slide' ); ?>><?php esc_html_e( 'Slide', 'privacypress' ); ?></option>
				<option value="fade" <?php selected( $pcm_banner['animation'], 'fade' ); ?>><?php esc_html_e( 'Fade', 'privacypress' ); ?></option>
				<option value="none" <?php selected( $pcm_banner['animation'], 'none' ); ?>><?php esc_html_e( 'None', 'privacypress' ); ?></option>
			</select>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Button labels', 'privacypress' ); ?></th>
		<td>
			<p><label><?php esc_html_e( 'Accept All', 'privacypress' ); ?><br /><input type="text" name="pcm[banner][accept_label]" value="<?php echo esc_attr( $pcm_banner['accept_label'] ); ?>" /></label></p>
			<p><label><?php esc_html_e( 'Reject All', 'privacypress' ); ?><br /><input type="text" name="pcm[banner][reject_label]" value="<?php echo esc_attr( $pcm_banner['reject_label'] ); ?>" /></label></p>
			<p><label><?php esc_html_e( 'Manage Preferences', 'privacypress' ); ?><br /><input type="text" name="pcm[banner][manage_label]" value="<?php echo esc_attr( $pcm_banner['manage_label'] ); ?>" /></label></p>
			<p><label><?php esc_html_e( 'Save Preferences', 'privacypress' ); ?><br /><input type="text" name="pcm[banner][save_label]" value="<?php echo esc_attr( $pcm_banner['save_label'] ); ?>" /></label></p>
			<p><label><?php esc_html_e( 'Reopen button', 'privacypress' ); ?><br /><input type="text" name="pcm[banner][reopen_label]" value="<?php echo esc_attr( $pcm_banner['reopen_label'] ); ?>" /></label></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Options', 'privacypress' ); ?></th>
		<td>
			<p><label><input type="hidden" name="pcm[banner][show_reject]" value="" /><input type="checkbox" name="pcm[banner][show_reject]" value="1" <?php checked( $pcm_banner['show_reject'] ); ?> /> <?php esc_html_e( 'Show "Reject All" button', 'privacypress' ); ?></label></p>
			<p><label><input type="hidden" name="pcm[banner][show_close]" value="" /><input type="checkbox" name="pcm[banner][show_close]" value="1" <?php checked( $pcm_banner['show_close'] ); ?> /> <?php esc_html_e( 'Show close button (only where legally appropriate)', 'privacypress' ); ?></label></p>
			<p><label><input type="hidden" name="pcm[banner][reopen_button]" value="" /><input type="checkbox" name="pcm[banner][reopen_button]" value="1" <?php checked( $pcm_banner['reopen_button'] ); ?> /> <?php esc_html_e( 'Show floating "Privacy Settings" reopen button', 'privacypress' ); ?></label></p>
			<p><label><input type="hidden" name="pcm[banner][hide_in_admin]" value="" /><input type="checkbox" name="pcm[banner][hide_in_admin]" value="1" <?php checked( $pcm_banner['hide_in_admin'] ); ?> /> <?php esc_html_e( 'Disable consent banner in WordPress Admin', 'privacypress' ); ?></label></p>
			<p><label><input type="hidden" name="pcm[banner][hide_in_elementor]" value="" /><input type="checkbox" name="pcm[banner][hide_in_elementor]" value="1" <?php checked( $pcm_banner['hide_in_elementor'] ); ?> /> <?php esc_html_e( 'Disable consent banner in Elementor Editor', 'privacypress' ); ?></label></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Colors', 'privacypress' ); ?></th>
		<td>
			<p><label><?php esc_html_e( 'Primary', 'privacypress' ); ?> <input type="color" name="pcm[banner][primary_color]" value="<?php echo esc_attr( $pcm_banner['primary_color'] ); ?>" /></label></p>
			<p><label><?php esc_html_e( 'Text', 'privacypress' ); ?> <input type="color" name="pcm[banner][text_color]" value="<?php echo esc_attr( $pcm_banner['text_color'] ); ?>" /></label></p>
			<p><label><?php esc_html_e( 'Background', 'privacypress' ); ?> <input type="color" name="pcm[banner][background_color]" value="<?php echo esc_attr( $pcm_banner['background_color'] ); ?>" /></label></p>
			<p><label><?php esc_html_e( 'Button text', 'privacypress' ); ?> <input type="color" name="pcm[banner][button_text_color]" value="<?php echo esc_attr( $pcm_banner['button_text_color'] ); ?>" /></label></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="pcm-font-size"><?php esc_html_e( 'Font size (px)', 'privacypress' ); ?></label></th>
		<td><input id="pcm-font-size" type="number" min="10" max="24" name="pcm[banner][font_size]" value="<?php echo esc_attr( $pcm_banner['font_size'] ); ?>" /></td>
	</tr>
	<tr>
		<th scope="row"><label for="pcm-radius"><?php esc_html_e( 'Border radius (px)', 'privacypress' ); ?></label></th>
		<td><input id="pcm-radius" type="number" min="0" max="40" name="pcm[banner][border_radius]" value="<?php echo esc_attr( $pcm_banner['border_radius'] ); ?>" /></td>
	</tr>
	<tr>
		<th scope="row"><label for="pcm-logo"><?php esc_html_e( 'Logo URL', 'privacypress' ); ?></label></th>
		<td><input id="pcm-logo" type="url" class="regular-text" name="pcm[banner][logo_url]" value="<?php echo esc_attr( $pcm_banner['logo_url'] ); ?>" /></td>
	</tr>
</table>

<?php Admin::form_close(); ?>
