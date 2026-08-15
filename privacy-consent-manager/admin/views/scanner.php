<?php
/**
 * Cookie/Script Scanner + duplicate tracking results.
 *
 * @package PCM
 * @var array                           $settings
 * @var PCM\Duplicate_Tracking_Detector $scanner
 */

defined( 'ABSPATH' ) || exit;

use PCM\Admin\Admin;

$pcm_scan = $scanner->last_scan();

Admin::maybe_notice();
?>
<h1><?php esc_html_e( 'Cookie / Script Scanner', 'privacy-consent-manager' ); ?></h1>

<p>
	<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=pcm_scan' ), 'pcm_scan', '_pcm_nonce' ) ); ?>">
		<?php esc_html_e( 'Scan Now', 'privacy-consent-manager' ); ?>
	</a>
	<?php if ( ! empty( $pcm_scan['time'] ) ) : ?>
		<span class="description">
			<?php
			printf(
				/* translators: %s: human-readable time difference */
				esc_html__( 'Last scan: %s ago', 'privacy-consent-manager' ),
				esc_html( human_time_diff( (int) $pcm_scan['time'] ) )
			);
			?>
		</span>
	<?php endif; ?>
</p>
<p class="description"><?php esc_html_e( 'The scanner fetches your own homepage and inspects it for known trackers and third-party scripts. It runs only when you click Scan Now.', 'privacy-consent-manager' ); ?></p>

<?php if ( ! empty( $pcm_scan['results'] ) ) : ?>
	<h2><?php esc_html_e( 'Duplicate Tracking', 'privacy-consent-manager' ); ?></h2>
	<table class="widefat striped pcm-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Service', 'privacy-consent-manager' ); ?></th>
				<th><?php esc_html_e( 'Instances on page', 'privacy-consent-manager' ); ?></th>
				<th><?php esc_html_e( 'Configured by', 'privacy-consent-manager' ); ?></th>
				<th><?php esc_html_e( 'Status', 'privacy-consent-manager' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $pcm_scan['results'] as $pcm_result ) : ?>
			<tr>
				<td><?php echo esc_html( $pcm_result['label'] ); ?></td>
				<td><?php echo esc_html( $pcm_result['instances'] ); ?></td>
				<td><?php echo esc_html( $pcm_result['sources'] ? implode( ', ', $pcm_result['sources'] ) : '—' ); ?></td>
				<td>
					<?php if ( ! empty( $pcm_result['duplicate'] ) ) : ?>
						<span class="pcm-badge pcm-badge-amber">⚠ <?php esc_html_e( 'Potential duplicate collection', 'privacy-consent-manager' ); ?></span>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcm-conflicts' ) ); ?>"><?php esc_html_e( 'Review', 'privacy-consent-manager' ); ?></a>
					<?php elseif ( $pcm_result['instances'] > 0 || $pcm_result['sources'] ) : ?>
						<span class="pcm-badge pcm-badge-green"><?php esc_html_e( 'OK', 'privacy-consent-manager' ); ?></span>
					<?php else : ?>
						<span class="pcm-badge"><?php esc_html_e( 'Not detected', 'privacy-consent-manager' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>

<?php if ( ! empty( $pcm_scan['scripts'] ) ) : ?>
	<h2><?php esc_html_e( 'Third-Party Scripts', 'privacy-consent-manager' ); ?></h2>
	<?php Admin::form_open( array( 'scanner' ) ); ?>
	<table class="widefat striped pcm-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Domain', 'privacy-consent-manager' ); ?></th>
				<th><?php esc_html_e( 'Category', 'privacy-consent-manager' ); ?></th>
				<th><?php esc_html_e( 'Classify', 'privacy-consent-manager' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $pcm_scan['scripts'] as $pcm_script ) : ?>
			<tr>
				<td><code><?php echo esc_html( $pcm_script['host'] ); ?></code></td>
				<td>
					<?php if ( 'unknown' === $pcm_script['category'] ) : ?>
						<span class="pcm-badge pcm-badge-amber">⚠ <?php esc_html_e( 'Unknown Tracker', 'privacy-consent-manager' ); ?></span>
					<?php else : ?>
						<span class="pcm-badge"><?php echo esc_html( $pcm_script['category'] ); ?></span>
					<?php endif; ?>
				</td>
				<td>
					<select name="pcm[scanner][classifications][<?php echo esc_attr( $pcm_script['host'] ); ?>]">
						<option value="unknown" <?php selected( $pcm_script['category'], 'unknown' ); ?>><?php esc_html_e( 'Unknown', 'privacy-consent-manager' ); ?></option>
						<?php foreach ( $settings['categories'] as $pcm_slug => $pcm_cat ) : ?>
							<option value="<?php echo esc_attr( $pcm_slug ); ?>" <?php selected( $pcm_script['category'], $pcm_slug ); ?>><?php echo esc_html( $pcm_cat['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<p class="description"><?php esc_html_e( 'Unknown trackers are not assumed harmless. Classifying a domain adds it to the script blocker so it is held until the visitor grants that category.', 'privacy-consent-manager' ); ?></p>
	<?php Admin::form_close(); ?>
<?php endif; ?>
