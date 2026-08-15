<?php
/**
 * Consent records browser.
 *
 * @package PCM
 * @var array               $settings
 * @var PCM\Consent_Storage $storage
 */

defined( 'ABSPATH' ) || exit;

use PCM\Admin\Admin;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- pagination only.
$pcm_page    = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
$pcm_records = $storage->get_records( $pcm_page, 50 );
$pcm_pages   = (int) ceil( $pcm_records['total'] / 50 );

Admin::maybe_notice();
?>
<h1><?php esc_html_e( 'Consent Records', 'privacy-consent-manager' ); ?></h1>
<p class="description">
	<?php esc_html_e( 'Records are stored without IP addresses or other direct identifiers. The anonymous ID is generated in the visitor\'s browser and shown to them in the preferences modal so they can reference their own consent.', 'privacy-consent-manager' ); ?>
</p>

<table class="widefat striped pcm-table">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Recorded (UTC)', 'privacy-consent-manager' ); ?></th>
			<th><?php esc_html_e( 'Consent ID', 'privacy-consent-manager' ); ?></th>
			<th><?php esc_html_e( 'Action', 'privacy-consent-manager' ); ?></th>
			<th><?php esc_html_e( 'Functional', 'privacy-consent-manager' ); ?></th>
			<th><?php esc_html_e( 'Analytics', 'privacy-consent-manager' ); ?></th>
			<th><?php esc_html_e( 'Marketing', 'privacy-consent-manager' ); ?></th>
			<th><?php esc_html_e( 'Preferences', 'privacy-consent-manager' ); ?></th>
			<th><?php esc_html_e( 'Version', 'privacy-consent-manager' ); ?></th>
			<th><?php esc_html_e( 'Region', 'privacy-consent-manager' ); ?></th>
		</tr>
	</thead>
	<tbody>
	<?php if ( empty( $pcm_records['items'] ) ) : ?>
		<tr><td colspan="9"><?php esc_html_e( 'No consent records yet.', 'privacy-consent-manager' ); ?></td></tr>
	<?php endif; ?>
	<?php foreach ( $pcm_records['items'] as $pcm_row ) : ?>
		<tr>
			<td><?php echo esc_html( $pcm_row['created_at'] ); ?></td>
			<td><code><?php echo esc_html( substr( $pcm_row['consent_id'], 0, 8 ) ); ?>…</code></td>
			<td><?php echo esc_html( $pcm_row['action'] ); ?></td>
			<?php foreach ( array( 'functional', 'analytics', 'marketing', 'preferences' ) as $pcm_cat ) : ?>
				<td><?php echo $pcm_row[ $pcm_cat ] ? '✅' : '—'; ?></td>
			<?php endforeach; ?>
			<td><code><?php echo esc_html( $pcm_row['consent_version'] ); ?></code></td>
			<td><?php echo esc_html( $pcm_row['region'] ?: '—' ); ?></td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>

<?php if ( $pcm_pages > 1 ) : ?>
	<p>
		<?php for ( $pcm_i = 1; $pcm_i <= min( $pcm_pages, 25 ); $pcm_i++ ) : ?>
			<?php if ( $pcm_i === $pcm_page ) : ?>
				<strong><?php echo esc_html( $pcm_i ); ?></strong>
			<?php else : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcm-records&paged=' . $pcm_i ) ); ?>"><?php echo esc_html( $pcm_i ); ?></a>
			<?php endif; ?>
		<?php endfor; ?>
	</p>
<?php endif; ?>

<p>
	<?php
	printf(
		/* translators: 1: total records, 2: retention days */
		esc_html__( 'Total: %1$s records. Records older than %2$d days are deleted automatically by a daily cleanup task.', 'privacy-consent-manager' ),
		esc_html( number_format_i18n( $pcm_records['total'] ) ),
		absint( $settings['consent']['retention_days'] )
	);
	?>
</p>
