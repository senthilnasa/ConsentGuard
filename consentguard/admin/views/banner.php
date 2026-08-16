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
<h1><?php esc_html_e( 'Consent Banner', 'consentguard' ); ?></h1>

<?php Admin::form_open( array( 'banner', 'consent' ) ); ?>

<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Enable banner', 'consentguard' ); ?></th>
		<td><label><input type="hidden" name="pcm[consent][banner_enabled]" value="" /><input type="checkbox" name="pcm[consent][banner_enabled]" value="1" <?php checked( $settings['consent']['banner_enabled'] ); ?> /> <?php esc_html_e( 'Show the consent banner to visitors without a stored decision', 'consentguard' ); ?></label></td>
	</tr>
	<tr>
		<th scope="row"><label for="pcm-banner-title"><?php esc_html_e( 'Title', 'consentguard' ); ?></label></th>
		<td><input id="pcm-banner-title" type="text" class="regular-text" name="pcm[banner][title]" value="<?php echo esc_attr( $pcm_banner['title'] ); ?>" /></td>
	</tr>
	<tr>
		<th scope="row"><label for="pcm-banner-message"><?php esc_html_e( 'Message', 'consentguard' ); ?></label></th>
		<td><textarea id="pcm-banner-message" class="large-text" rows="3" name="pcm[banner][message]"><?php echo esc_textarea( $pcm_banner['message'] ); ?></textarea></td>
	</tr>
	<tr>
		<th scope="row"><label for="pcm-preferences-intro"><?php esc_html_e( 'Preferences modal introduction', 'consentguard' ); ?></label></th>
		<td>
			<textarea id="pcm-preferences-intro" class="large-text" rows="3" name="pcm[banner][preferences_intro]"><?php echo esc_textarea( $pcm_banner['preferences_intro'] ?? '' ); ?></textarea>
			<p class="description"><?php esc_html_e( 'Shown at the top of the Customise Consent Preferences modal. Long texts collapse behind a "Show more" link automatically.', 'consentguard' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Match site theme', 'consentguard' ); ?></th>
		<td>
			<label><input type="hidden" name="pcm[banner][use_theme_colors]" value="" /><input type="checkbox" name="pcm[banner][use_theme_colors]" value="1" <?php checked( ! empty( $pcm_banner['use_theme_colors'] ) ); ?> /> <?php esc_html_e( 'Derive the primary/background/text colors from the active theme\'s palette (block themes). The colors below act as fallback.', 'consentguard' ); ?></label>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Position', 'consentguard' ); ?></th>
		<td>
			<select name="pcm[banner][position]">
				<?php
				$pcm_positions = array(
					'bottom'       => __( 'Bottom bar', 'consentguard' ),
					'top'          => __( 'Top bar', 'consentguard' ),
					'bottom-left'  => __( 'Bottom left box', 'consentguard' ),
					'bottom-right' => __( 'Bottom right box', 'consentguard' ),
					'center'       => __( 'Center modal', 'consentguard' ),
				);
				foreach ( $pcm_positions as $pcm_value => $pcm_label ) :
					?>
					<option value="<?php echo esc_attr( $pcm_value ); ?>" <?php selected( $pcm_banner['position'], $pcm_value ); ?>><?php echo esc_html( $pcm_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Layout', 'consentguard' ); ?></th>
		<td>
			<select name="pcm[banner][layout]">
				<option value="bar" <?php selected( $pcm_banner['layout'], 'bar' ); ?>><?php esc_html_e( 'Bar', 'consentguard' ); ?></option>
				<option value="box" <?php selected( $pcm_banner['layout'], 'box' ); ?>><?php esc_html_e( 'Box', 'consentguard' ); ?></option>
			</select>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Theme', 'consentguard' ); ?></th>
		<td>
			<select name="pcm[banner][theme]">
				<option value="light" <?php selected( $pcm_banner['theme'] ?? 'light', 'light' ); ?>><?php esc_html_e( 'Light', 'consentguard' ); ?></option>
				<option value="dark" <?php selected( $pcm_banner['theme'] ?? 'light', 'dark' ); ?>><?php esc_html_e( 'Dark', 'consentguard' ); ?></option>
				<option value="auto" <?php selected( $pcm_banner['theme'] ?? 'light', 'auto' ); ?>><?php esc_html_e( 'Auto (follow visitor system preference)', 'consentguard' ); ?></option>
			</select>
			<p class="description"><?php esc_html_e( 'Dark and Auto themes ignore the background/text color settings and use a polished dark palette; the primary color is kept.', 'consentguard' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Animation', 'consentguard' ); ?></th>
		<td>
			<select name="pcm[banner][animation]">
				<option value="slide" <?php selected( $pcm_banner['animation'], 'slide' ); ?>><?php esc_html_e( 'Slide', 'consentguard' ); ?></option>
				<option value="fade" <?php selected( $pcm_banner['animation'], 'fade' ); ?>><?php esc_html_e( 'Fade', 'consentguard' ); ?></option>
				<option value="none" <?php selected( $pcm_banner['animation'], 'none' ); ?>><?php esc_html_e( 'None', 'consentguard' ); ?></option>
			</select>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Button labels', 'consentguard' ); ?></th>
		<td>
			<p><label><?php esc_html_e( 'Accept All', 'consentguard' ); ?><br /><input type="text" name="pcm[banner][accept_label]" value="<?php echo esc_attr( $pcm_banner['accept_label'] ); ?>" /></label></p>
			<p><label><?php esc_html_e( 'Reject All', 'consentguard' ); ?><br /><input type="text" name="pcm[banner][reject_label]" value="<?php echo esc_attr( $pcm_banner['reject_label'] ); ?>" /></label></p>
			<p><label><?php esc_html_e( 'Manage Preferences', 'consentguard' ); ?><br /><input type="text" name="pcm[banner][manage_label]" value="<?php echo esc_attr( $pcm_banner['manage_label'] ); ?>" /></label></p>
			<p><label><?php esc_html_e( 'Save Preferences', 'consentguard' ); ?><br /><input type="text" name="pcm[banner][save_label]" value="<?php echo esc_attr( $pcm_banner['save_label'] ); ?>" /></label></p>
			<p><label><?php esc_html_e( 'Reopen button', 'consentguard' ); ?><br /><input type="text" name="pcm[banner][reopen_label]" value="<?php echo esc_attr( $pcm_banner['reopen_label'] ); ?>" /></label></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Options', 'consentguard' ); ?></th>
		<td>
			<p><label><input type="hidden" name="pcm[banner][show_reject]" value="" /><input type="checkbox" name="pcm[banner][show_reject]" value="1" <?php checked( $pcm_banner['show_reject'] ); ?> /> <?php esc_html_e( 'Show "Reject All" button', 'consentguard' ); ?></label></p>
			<p><label><input type="hidden" name="pcm[banner][show_close]" value="" /><input type="checkbox" name="pcm[banner][show_close]" value="1" <?php checked( $pcm_banner['show_close'] ); ?> /> <?php esc_html_e( 'Show close button (only where legally appropriate)', 'consentguard' ); ?></label></p>
			<p><label><input type="hidden" name="pcm[banner][reopen_button]" value="" /><input type="checkbox" name="pcm[banner][reopen_button]" value="1" <?php checked( $pcm_banner['reopen_button'] ); ?> /> <?php esc_html_e( 'Show floating "Privacy Settings" reopen button', 'consentguard' ); ?></label></p>
			<p><label><input type="hidden" name="pcm[banner][hide_in_admin]" value="" /><input type="checkbox" name="pcm[banner][hide_in_admin]" value="1" <?php checked( $pcm_banner['hide_in_admin'] ); ?> /> <?php esc_html_e( 'Disable consent banner in WordPress Admin', 'consentguard' ); ?></label></p>
			<p><label><input type="hidden" name="pcm[banner][hide_in_elementor]" value="" /><input type="checkbox" name="pcm[banner][hide_in_elementor]" value="1" <?php checked( $pcm_banner['hide_in_elementor'] ); ?> /> <?php esc_html_e( 'Disable consent banner in Elementor Editor', 'consentguard' ); ?></label></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Colors', 'consentguard' ); ?></th>
		<td>
			<p><label><?php esc_html_e( 'Primary', 'consentguard' ); ?> <input type="color" name="pcm[banner][primary_color]" value="<?php echo esc_attr( $pcm_banner['primary_color'] ); ?>" /></label></p>
			<p><label><?php esc_html_e( 'Text', 'consentguard' ); ?> <input type="color" name="pcm[banner][text_color]" value="<?php echo esc_attr( $pcm_banner['text_color'] ); ?>" /></label></p>
			<p><label><?php esc_html_e( 'Background', 'consentguard' ); ?> <input type="color" name="pcm[banner][background_color]" value="<?php echo esc_attr( $pcm_banner['background_color'] ); ?>" /></label></p>
			<p><label><?php esc_html_e( 'Button text', 'consentguard' ); ?> <input type="color" name="pcm[banner][button_text_color]" value="<?php echo esc_attr( $pcm_banner['button_text_color'] ); ?>" /></label></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="pcm-font-size"><?php esc_html_e( 'Font size (px)', 'consentguard' ); ?></label></th>
		<td><input id="pcm-font-size" type="number" min="10" max="24" name="pcm[banner][font_size]" value="<?php echo esc_attr( $pcm_banner['font_size'] ); ?>" /></td>
	</tr>
	<tr>
		<th scope="row"><label for="pcm-radius"><?php esc_html_e( 'Border radius (px)', 'consentguard' ); ?></label></th>
		<td><input id="pcm-radius" type="number" min="0" max="40" name="pcm[banner][border_radius]" value="<?php echo esc_attr( $pcm_banner['border_radius'] ); ?>" /></td>
	</tr>
	<tr>
		<th scope="row"><label for="pcm-logo"><?php esc_html_e( 'Logo URL', 'consentguard' ); ?></label></th>
		<td><input id="pcm-logo" type="url" class="regular-text" name="pcm[banner][logo_url]" value="<?php echo esc_attr( $pcm_banner['logo_url'] ); ?>" />
		<p class="description"><?php esc_html_e( 'Shown in the banner and in the preferences modal header.', 'consentguard' ); ?></p></td>
	</tr>
</table>

<h2><?php esc_html_e( 'Floating Revisit Widget', 'consentguard' ); ?></h2>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Show widget', 'consentguard' ); ?></th>
		<td><label><input type="hidden" name="pcm[banner][reopen_button]" value="" /><input type="checkbox" name="pcm[banner][reopen_button]" value="1" <?php checked( $pcm_banner['reopen_button'] ); ?> /> <?php esc_html_e( 'Show the floating consent-revisit button after a decision was made', 'consentguard' ); ?></label></td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Default position', 'consentguard' ); ?></th>
		<td>
			<select name="pcm[banner][reopen_position]">
				<?php
				$pcm_reopen_positions = array(
					'bottom-left'  => __( 'Bottom left', 'consentguard' ),
					'bottom-right' => __( 'Bottom right', 'consentguard' ),
					'top-left'     => __( 'Top left', 'consentguard' ),
					'top-right'    => __( 'Top right', 'consentguard' ),
				);
				foreach ( $pcm_reopen_positions as $pcm_value => $pcm_label ) :
					?>
					<option value="<?php echo esc_attr( $pcm_value ); ?>" <?php selected( $pcm_banner['reopen_position'] ?? 'bottom-left', $pcm_value ); ?>><?php echo esc_html( $pcm_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Draggable', 'consentguard' ); ?></th>
		<td><label><input type="hidden" name="pcm[banner][reopen_draggable]" value="" /><input type="checkbox" name="pcm[banner][reopen_draggable]" value="1" <?php checked( ! empty( $pcm_banner['reopen_draggable'] ) ); ?> /> <?php esc_html_e( 'Let visitors drag the widget anywhere on screen (their position is remembered in their browser)', 'consentguard' ); ?></label></td>
	</tr>
	<tr>
		<th scope="row"><label for="pcm-reopen-icon"><?php esc_html_e( 'Widget logo URL', 'consentguard' ); ?></label></th>
		<td>
			<input id="pcm-reopen-icon" type="url" class="regular-text" name="pcm[banner][reopen_icon_url]" value="<?php echo esc_attr( $pcm_banner['reopen_icon_url'] ?? '' ); ?>" />
			<p class="description"><?php esc_html_e( 'Optional custom icon/logo for the widget. Leave empty for the built-in cookie icon.', 'consentguard' ); ?></p>
		</td>
	</tr>
</table>

<?php Admin::form_close(); ?>
