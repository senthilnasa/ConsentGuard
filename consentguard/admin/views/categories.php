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
<h1><?php esc_html_e( 'Consent Categories', 'consentguard' ); ?></h1>
<p><?php esc_html_e( 'Built-in categories can be re-labelled but not removed. Add extra categories at the bottom; leave a label empty to delete a custom category.', 'consentguard' ); ?></p>

<?php Admin::form_open( array( 'categories' ) ); ?>

<table class="widefat striped pcm-table">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Slug', 'consentguard' ); ?></th>
			<th><?php esc_html_e( 'Label', 'consentguard' ); ?></th>
			<th><?php esc_html_e( 'Description', 'consentguard' ); ?></th>
			<th><?php esc_html_e( 'Required', 'consentguard' ); ?></th>
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
					<span class="pcm-badge pcm-badge-green"><?php esc_html_e( 'Always', 'consentguard' ); ?></span>
				<?php else : ?>
					<input type="hidden" name="pcm[categories][<?php echo esc_attr( $pcm_slug ); ?>][required]" value="" /><input type="checkbox" name="pcm[categories][<?php echo esc_attr( $pcm_slug ); ?>][required]" value="1" <?php checked( ! empty( $pcm_cat['required'] ) ); ?> />
				<?php endif; ?>
			</td>
		</tr>
	<?php endforeach; ?>
		<tr>
			<td><input type="text" name="pcm_new_category_slug" placeholder="<?php esc_attr_e( 'new-category', 'consentguard' ); ?>" /></td>
			<td><input type="text" name="pcm_new_category_label" placeholder="<?php esc_attr_e( 'New Category', 'consentguard' ); ?>" /></td>
			<td><input type="text" class="large-text" name="pcm_new_category_description" /></td>
			<td></td>
		</tr>
	</tbody>
</table>
<p class="description"><?php esc_html_e( 'New categories default to disabled until the visitor grants them.', 'consentguard' ); ?></p>

<h2><?php esc_html_e( 'Cookie details per category', 'consentguard' ); ?></h2>
<p class="description"><?php esc_html_e( 'These entries are shown to visitors inside each category of the preferences modal and in the proof-of-consent PDF. One cookie per line, three parts separated by | (pipe): name | duration | description.', 'consentguard' ); ?></p>

<?php $pcm_inventory = $settings['cookies'] ?? array(); ?>
<?php foreach ( $pcm_categories as $pcm_slug => $pcm_cat ) : ?>
	<h3><?php echo esc_html( $pcm_cat['label'] ); ?> <code><?php echo esc_html( $pcm_slug ); ?></code></h3>
	<textarea class="large-text code" rows="4" name="pcm_cookie_inventory[<?php echo esc_attr( $pcm_slug ); ?>]" placeholder="_ga | 1 year | Google Analytics visitor cookie"><?php
	foreach ( (array) ( $pcm_inventory[ $pcm_slug ] ?? array() ) as $pcm_cookie ) {
		echo esc_textarea( $pcm_cookie['name'] . ' | ' . $pcm_cookie['duration'] . ' | ' . $pcm_cookie['description'] . "\n" );
	}
	?></textarea>
<?php endforeach; ?>

<?php Admin::form_close(); ?>
