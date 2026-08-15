<?php
/**
 * Dashboard view.
 *
 * @package PCM
 * @var array                            $settings
 * @var PCM\Plugin_Conflict_Manager      $conflicts
 * @var PCM\Duplicate_Tracking_Detector  $scanner
 * @var PCM\Consent_Storage              $storage
 */

defined( 'ABSPATH' ) || exit;

use PCM\Admin\Admin;

$pcm_stats     = $storage->get_stats();
$pcm_open      = $conflicts->get_open_conflicts();
$pcm_dupes     = $scanner->duplicate_count();
$pcm_statuses  = pcm()->module( 'analytics' ) ? pcm()->module( 'analytics' )->statuses() : array();
$pcm_total     = max( 1, $pcm_stats['total'] );

Admin::maybe_notice();
?>
<h1><?php esc_html_e( 'PrivacyPress', 'privacypress' ); ?></h1>

<div class="pcm-cards">
	<div class="pcm-card">
		<h2><?php esc_html_e( 'Consent', 'privacypress' ); ?></h2>
		<table class="widefat striped">
			<tbody>
				<tr><td><?php esc_html_e( 'Total consent records', 'privacypress' ); ?></td><td><strong><?php echo esc_html( number_format_i18n( $pcm_stats['total'] ) ); ?></strong></td></tr>
				<tr><td><?php esc_html_e( 'Accepted all', 'privacypress' ); ?></td><td><?php echo esc_html( round( 100 * $pcm_stats['accepted_all'] / $pcm_total ) ); ?>%</td></tr>
				<tr><td><?php esc_html_e( 'Rejected all', 'privacypress' ); ?></td><td><?php echo esc_html( round( 100 * $pcm_stats['rejected_all'] / $pcm_total ) ); ?>%</td></tr>
				<tr><td><?php esc_html_e( 'Customized', 'privacypress' ); ?></td><td><?php echo esc_html( round( 100 * $pcm_stats['customized'] / $pcm_total ) ); ?>%</td></tr>
			</tbody>
		</table>
	</div>

	<div class="pcm-card">
		<h2><?php esc_html_e( 'Analytics', 'privacypress' ); ?></h2>
		<table class="widefat striped">
			<tbody>
			<?php
			$pcm_labels = array(
				'ga4'        => __( 'Google Analytics 4', 'privacypress' ),
				'gtm'        => __( 'Google Tag Manager', 'privacypress' ),
				'clarity'    => __( 'Microsoft Clarity', 'privacypress' ),
				'cloudflare' => __( 'Cloudflare Analytics', 'privacypress' ),
			);
			foreach ( $pcm_labels as $pcm_key => $pcm_label ) :
				$pcm_status = isset( $pcm_statuses[ $pcm_key ] ) ? $pcm_statuses[ $pcm_key ] : array( 'configured' => false, 'enabled' => false );
				?>
				<tr>
					<td><?php echo esc_html( $pcm_label ); ?></td>
					<td>
						<?php if ( ! empty( $pcm_status['configured'] ) ) : ?>
							<span class="pcm-badge pcm-badge-green"><?php esc_html_e( 'Active', 'privacypress' ); ?></span>
						<?php elseif ( ! empty( $pcm_status['enabled'] ) ) : ?>
							<span class="pcm-badge pcm-badge-amber"><?php esc_html_e( 'Enabled, not configured', 'privacypress' ); ?></span>
						<?php else : ?>
							<span class="pcm-badge"><?php esc_html_e( 'Off', 'privacypress' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<div class="pcm-card">
		<h2><?php esc_html_e( 'Conflicts', 'privacypress' ); ?></h2>
		<table class="widefat striped">
			<tbody>
				<tr>
					<td><?php esc_html_e( 'Plugin conflicts', 'privacypress' ); ?></td>
					<td>
						<?php if ( count( $pcm_open ) > 0 ) : ?>
							<span class="pcm-badge pcm-badge-amber">⚠ <?php echo esc_html( count( $pcm_open ) ); ?></span>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcm-conflicts' ) ); ?>"><?php esc_html_e( 'Review', 'privacypress' ); ?></a>
						<?php else : ?>
							<span class="pcm-badge pcm-badge-green">0</span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Duplicate trackers', 'privacypress' ); ?></td>
					<td>
						<?php if ( $pcm_dupes > 0 ) : ?>
							<span class="pcm-badge pcm-badge-amber">⚠ <?php echo esc_html( $pcm_dupes ); ?></span>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcm-scanner' ) ); ?>"><?php esc_html_e( 'Review', 'privacypress' ); ?></a>
						<?php else : ?>
							<span class="pcm-badge pcm-badge-green">0</span>
						<?php endif; ?>
					</td>
				</tr>
			</tbody>
		</table>
	</div>

	<div class="pcm-card">
		<h2><?php esc_html_e( 'Privacy', 'privacypress' ); ?></h2>
		<table class="widefat striped">
			<tbody>
				<tr>
					<td><?php esc_html_e( 'Consent version', 'privacypress' ); ?></td>
					<td><code><?php echo esc_html( $settings['consent']['consent_version'] ); ?></code></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Policy version', 'privacypress' ); ?></td>
					<td><code><?php echo esc_html( $settings['policies']['policy_version'] ); ?></code></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Consent record retention', 'privacypress' ); ?></td>
					<td>
						<?php
						printf(
							/* translators: %d: number of days */
							esc_html__( '%d days', 'privacypress' ),
							absint( $settings['consent']['retention_days'] )
						);
						?>
					</td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Privacy policy page', 'privacypress' ); ?></td>
					<td>
						<?php if ( ! empty( $settings['policies']['privacy_page_id'] ) ) : ?>
							<span class="pcm-badge pcm-badge-green"><?php esc_html_e( 'Configured', 'privacypress' ); ?></span>
						<?php else : ?>
							<span class="pcm-badge pcm-badge-amber"><?php esc_html_e( 'Not set', 'privacypress' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
</div>
