<?php
/**
 * Consent categories editor.
 *
 * @package PCM
 * @var array $settings
 */

defined( 'ABSPATH' ) || exit;

use PCM\Admin\Admin;

$pcm_categories = $settings['categories'];

Admin::maybe_notice();
?>
<h1><?php esc_html_e( 'Consent Categories', 'privacy-consent-manager' ); ?></h1>
<p><?php esc_html_e( 'Built-in categories can be re-labelled but not removed. Add extra categories at the bottom; leave a label empty to delete a custom category.', 'privacy-consent-manager' ); ?></p>

<?php Admin::form_open( array( 'categories' ) ); ?>

<table class="widefat striped pcm-table">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Slug', 'privacy-consent-manager' ); ?></th>
			<th><?php esc_html_e( 'Label', 'privacy-consent-manager' ); ?></th>
			<th><?php esc_html_e( 'Description', 'privacy-consent-manager' ); ?></th>
			<th><?php esc_html_e( 'Required', 'privacy-consent-manager' ); ?></th>
		</tr>
	</thead>
	<tbody>
	<?php foreach ( $pcm_categories as $pcm_slug => $pcm_cat ) : ?>
		<tr>
			<td><code><?php echo esc_html( $pcm_slug ); ?></code></td>
			<td><input type="text" name="pcm[categories][<?php echo esc_attr( $pcm_slug ); ?>][label]" value="<?php echo esc_attr( $pcm_cat['label'] ); ?>" /></td>
			<td><input type="text" class="large-text" name="pcm[categories][<?php echo esc_attr( $pcm_slug ); ?>][description]" value="<?php echo esc_attr( $pcm_cat['description'] ); ?>" /></td>
			<td>
				<?php if ( 'necessary' === $pcm_slug ) : ?>
					<span class="pcm-badge pcm-badge-green"><?php esc_html_e( 'Always', 'privacy-consent-manager' ); ?></span>
				<?php else : ?>
					<input type="checkbox" name="pcm[categories][<?php echo esc_attr( $pcm_slug ); ?>][required]" value="1" <?php checked( ! empty( $pcm_cat['required'] ) ); ?> />
				<?php endif; ?>
			</td>
		</tr>
	<?php endforeach; ?>
		<tr>
			<td><input type="text" name="pcm_new_category_slug" placeholder="<?php esc_attr_e( 'new-category', 'privacy-consent-manager' ); ?>" /></td>
			<td><input type="text" name="pcm_new_category_label" placeholder="<?php esc_attr_e( 'New Category', 'privacy-consent-manager' ); ?>" /></td>
			<td><input type="text" class="large-text" name="pcm_new_category_description" /></td>
			<td></td>
		</tr>
	</tbody>
</table>
<p class="description"><?php esc_html_e( 'New categories default to disabled until the visitor grants them.', 'privacy-consent-manager' ); ?></p>

<?php Admin::form_close(); ?>
