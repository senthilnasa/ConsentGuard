<?php
/**
 * Custom Script Manager.
 *
 * @package PCM
 * @var array $settings
 */

defined( 'ABSPATH' ) || exit;

use PCM\Admin\Admin;

$pcm_scripts    = $settings['custom_scripts'];
$pcm_categories = $settings['categories'];
$pcm_positions  = array(
	'header' => __( 'Header', 'consentguard' ),
	'body'   => __( 'Body (after opening body tag)', 'consentguard' ),
	'footer' => __( 'Footer', 'consentguard' ),
);

Admin::maybe_notice();

if ( ! current_user_can( 'unfiltered_html' ) ) {
	echo '<div class="notice notice-warning"><p>' . esc_html__( 'Your account lacks the unfiltered_html capability: script tags you save will be stripped to safe HTML. Ask a super administrator to add executable scripts.', 'consentguard' ) . '</p></div>';
}
?>
<h1><?php esc_html_e( 'Script Manager', 'consentguard' ); ?></h1>
<p><?php esc_html_e( 'Custom scripts are printed in consent-blocked form and execute only after the visitor grants the selected category. Empty the name to delete a script.', 'consentguard' ); ?></p>

<?php Admin::form_open( array( 'custom_scripts' ) ); ?>

<?php
$pcm_rows = $pcm_scripts;
// One blank row for adding a script.
$pcm_rows[] = array(
	'id'       => '',
	'name'     => '',
	'category' => 'marketing',
	'position' => 'footer',
	'enabled'  => true,
	'code'     => '',
);
foreach ( $pcm_rows as $pcm_i => $pcm_script ) :
	$pcm_is_new = '' === ( $pcm_script['name'] ?? '' );
	?>
	<div class="pcm-card pcm-script-row">
		<h2><?php echo $pcm_is_new ? esc_html__( 'Add Script', 'consentguard' ) : esc_html( $pcm_script['name'] ); ?></h2>
		<input type="hidden" name="pcm[custom_scripts][<?php echo esc_attr( $pcm_i ); ?>][id]" value="<?php echo esc_attr( $pcm_script['id'] ); ?>" />
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Name', 'consentguard' ); ?></th>
				<td><input type="text" class="regular-text" name="pcm[custom_scripts][<?php echo esc_attr( $pcm_i ); ?>][name]" value="<?php echo esc_attr( $pcm_script['name'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Facebook Pixel', 'consentguard' ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Category', 'consentguard' ); ?></th>
				<td>
					<select name="pcm[custom_scripts][<?php echo esc_attr( $pcm_i ); ?>][category]">
						<?php foreach ( $pcm_categories as $pcm_slug => $pcm_cat ) : ?>
							<option value="<?php echo esc_attr( $pcm_slug ); ?>" <?php selected( $pcm_script['category'], $pcm_slug ); ?>><?php echo esc_html( $pcm_cat['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Position', 'consentguard' ); ?></th>
				<td>
					<select name="pcm[custom_scripts][<?php echo esc_attr( $pcm_i ); ?>][position]">
						<?php foreach ( $pcm_positions as $pcm_value => $pcm_label ) : ?>
							<option value="<?php echo esc_attr( $pcm_value ); ?>" <?php selected( $pcm_script['position'], $pcm_value ); ?>><?php echo esc_html( $pcm_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Enabled', 'consentguard' ); ?></th>
				<td><input type="hidden" name="pcm[custom_scripts][<?php echo esc_attr( $pcm_i ); ?>][enabled]" value="" /><input type="checkbox" name="pcm[custom_scripts][<?php echo esc_attr( $pcm_i ); ?>][enabled]" value="1" <?php checked( ! empty( $pcm_script['enabled'] ) ); ?> /></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Script', 'consentguard' ); ?></th>
				<td>
					<textarea class="large-text code" rows="6" name="pcm[custom_scripts][<?php echo esc_attr( $pcm_i ); ?>][code]" placeholder="&lt;script&gt;...&lt;/script&gt;"><?php echo esc_textarea( $pcm_script['code'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Inline snippets, external script tags and tracking pixels are supported.', 'consentguard' ); ?></p>
				</td>
			</tr>
		</table>
	</div>
<?php endforeach; ?>

<?php Admin::form_close(); ?>
