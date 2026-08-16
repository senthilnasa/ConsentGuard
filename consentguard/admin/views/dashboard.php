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

$pcm_stats    = $storage->get_stats();
$pcm_open     = $conflicts->get_open_conflicts();
$pcm_dupes    = $scanner->duplicate_count();
$pcm_statuses = pcm()->module( 'analytics' ) ? pcm()->module( 'analytics' )->statuses() : array();
$pcm_total    = max( 1, $pcm_stats['total'] );

Admin::maybe_notice();

if ( $pcm_stats['total'] > 500000 ) {
	printf(
		'<div class="notice notice-warning"><p>%s</p></div>',
		esc_html__( 'The consent records table holds more than 500,000 rows. Consider lowering the retention period (Settings) so cleanup keeps the table lean.', 'consentguard' )
	);
}
?>
<h1><?php esc_html_e( 'ConsentGuard', 'consentguard' ); ?></h1>

<?php
// 30-day consent trend, rendered as a dependency-free stacked bar SVG.
$pcm_daily = $storage->get_daily_stats( 30 );
$pcm_max   = 1;
foreach ( $pcm_daily as $pcm_day ) {
	$pcm_max = max( $pcm_max, $pcm_day['accept'] + $pcm_day['reject'] + $pcm_day['custom'] );
}
$pcm_has_data = array_sum(
	array_map(
		static function ( $d ) {
			return $d['accept'] + $d['reject'] + $d['custom'];
		},
		$pcm_daily
	)
) > 0;
if ( $pcm_has_data ) :
	$pcm_w = 24;
	$pcm_h = 120;
	?>
	<div class="pcm-card">
		<h2><?php esc_html_e( 'Consent decisions — last 30 days', 'consentguard' ); ?></h2>
		<svg viewBox="0 0 <?php echo esc_attr( count( $pcm_daily ) * $pcm_w ); ?> <?php echo esc_attr( $pcm_h + 18 ); ?>" width="100%" height="150" role="img"
			aria-label="<?php esc_attr_e( 'Stacked daily bars of accepted, customized and rejected consents over the last 30 days', 'consentguard' ); ?>">
			<?php foreach ( $pcm_daily as $pcm_i => $pcm_day ) : ?>
				<?php
				$pcm_x  = $pcm_i * $pcm_w + 3;
				$pcm_ha = round( $pcm_h * $pcm_day['accept'] / $pcm_max );
				$pcm_hc = round( $pcm_h * $pcm_day['custom'] / $pcm_max );
				$pcm_hr = round( $pcm_h * $pcm_day['reject'] / $pcm_max );
				$pcm_y  = $pcm_h;
				?>
				<g>
					<title><?php echo esc_html( $pcm_day['date'] . ' — ✓' . $pcm_day['accept'] . ' ~' . $pcm_day['custom'] . ' ✕' . $pcm_day['reject'] ); ?></title>
					<?php $pcm_y -= $pcm_ha; ?>
					<rect x="<?php echo esc_attr( $pcm_x ); ?>" y="<?php echo esc_attr( $pcm_y ); ?>" width="<?php echo esc_attr( $pcm_w - 6 ); ?>" height="<?php echo esc_attr( $pcm_ha ); ?>" fill="#22a06b" rx="1"></rect>
					<?php $pcm_y -= $pcm_hc; ?>
					<rect x="<?php echo esc_attr( $pcm_x ); ?>" y="<?php echo esc_attr( $pcm_y ); ?>" width="<?php echo esc_attr( $pcm_w - 6 ); ?>" height="<?php echo esc_attr( $pcm_hc ); ?>" fill="#e2a400" rx="1"></rect>
					<?php $pcm_y -= $pcm_hr; ?>
					<rect x="<?php echo esc_attr( $pcm_x ); ?>" y="<?php echo esc_attr( $pcm_y ); ?>" width="<?php echo esc_attr( $pcm_w - 6 ); ?>" height="<?php echo esc_attr( $pcm_hr ); ?>" fill="#c9372c" rx="1"></rect>
				</g>
			<?php endforeach; ?>
		</svg>
		<p class="description">
			<span style="color:#22a06b">■</span> <?php esc_html_e( 'Accepted all', 'consentguard' ); ?>
			&nbsp;<span style="color:#e2a400">■</span> <?php esc_html_e( 'Customized', 'consentguard' ); ?>
			&nbsp;<span style="color:#c9372c">■</span> <?php esc_html_e( 'Rejected / withdrawn', 'consentguard' ); ?>
		</p>
	</div>
<?php endif; ?>

<div class="pcm-cards">
	<div class="pcm-card">
		<h2><?php esc_html_e( 'Consent', 'consentguard' ); ?></h2>
		<table class="widefat striped">
			<tbody>
				<tr><td><?php esc_html_e( 'Total consent records', 'consentguard' ); ?></td><td><strong><?php echo esc_html( number_format_i18n( $pcm_stats['total'] ) ); ?></strong></td></tr>
				<tr><td><?php esc_html_e( 'Accepted all', 'consentguard' ); ?></td><td><?php echo esc_html( round( 100 * $pcm_stats['accepted_all'] / $pcm_total ) ); ?>%</td></tr>
				<tr><td><?php esc_html_e( 'Rejected all', 'consentguard' ); ?></td><td><?php echo esc_html( round( 100 * $pcm_stats['rejected_all'] / $pcm_total ) ); ?>%</td></tr>
				<tr><td><?php esc_html_e( 'Customized', 'consentguard' ); ?></td><td><?php echo esc_html( round( 100 * $pcm_stats['customized'] / $pcm_total ) ); ?>%</td></tr>
			</tbody>
		</table>
	</div>

	<div class="pcm-card">
		<h2><?php esc_html_e( 'Analytics', 'consentguard' ); ?></h2>
		<table class="widefat striped">
			<tbody>
			<?php
			$pcm_labels = array(
				'ga4'        => __( 'Google Analytics 4', 'consentguard' ),
				'gtm'        => __( 'Google Tag Manager', 'consentguard' ),
				'clarity'    => __( 'Microsoft Clarity', 'consentguard' ),
				'cloudflare' => __( 'Cloudflare Analytics', 'consentguard' ),
			);
			foreach ( $pcm_labels as $pcm_key => $pcm_label ) :
				$pcm_status = isset( $pcm_statuses[ $pcm_key ] ) ? $pcm_statuses[ $pcm_key ] : array(
					'configured' => false,
					'enabled'    => false,
				);
				?>
				<tr>
					<td><?php echo esc_html( $pcm_label ); ?></td>
					<td>
						<?php if ( ! empty( $pcm_status['configured'] ) ) : ?>
							<span class="pcm-badge pcm-badge-green"><?php esc_html_e( 'Active', 'consentguard' ); ?></span>
						<?php elseif ( ! empty( $pcm_status['enabled'] ) ) : ?>
							<span class="pcm-badge pcm-badge-amber"><?php esc_html_e( 'Enabled, not configured', 'consentguard' ); ?></span>
						<?php else : ?>
							<span class="pcm-badge"><?php esc_html_e( 'Off', 'consentguard' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<div class="pcm-card">
		<h2><?php esc_html_e( 'Conflicts', 'consentguard' ); ?></h2>
		<table class="widefat striped">
			<tbody>
				<tr>
					<td><?php esc_html_e( 'Plugin conflicts', 'consentguard' ); ?></td>
					<td>
						<?php if ( count( $pcm_open ) > 0 ) : ?>
							<span class="pcm-badge pcm-badge-amber">⚠ <?php echo esc_html( count( $pcm_open ) ); ?></span>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcm-conflicts' ) ); ?>"><?php esc_html_e( 'Review', 'consentguard' ); ?></a>
						<?php else : ?>
							<span class="pcm-badge pcm-badge-green">0</span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Duplicate trackers', 'consentguard' ); ?></td>
					<td>
						<?php if ( $pcm_dupes > 0 ) : ?>
							<span class="pcm-badge pcm-badge-amber">⚠ <?php echo esc_html( $pcm_dupes ); ?></span>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=pcm-scanner' ) ); ?>"><?php esc_html_e( 'Review', 'consentguard' ); ?></a>
						<?php else : ?>
							<span class="pcm-badge pcm-badge-green">0</span>
						<?php endif; ?>
					</td>
				</tr>
			</tbody>
		</table>
	</div>

	<div class="pcm-card">
		<h2><?php esc_html_e( 'Privacy', 'consentguard' ); ?></h2>
		<table class="widefat striped">
			<tbody>
				<tr>
					<td><?php esc_html_e( 'Consent version', 'consentguard' ); ?></td>
					<td><code><?php echo esc_html( $settings['consent']['consent_version'] ); ?></code></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Policy version', 'consentguard' ); ?></td>
					<td><code><?php echo esc_html( $settings['policies']['policy_version'] ); ?></code></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Consent record retention', 'consentguard' ); ?></td>
					<td>
						<?php
						printf(
							/* translators: %d: number of days */
							esc_html__( '%d days', 'consentguard' ),
							absint( $settings['consent']['retention_days'] )
						);
						?>
					</td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Privacy policy page', 'consentguard' ); ?></td>
					<td>
						<?php if ( ! empty( $settings['policies']['privacy_page_id'] ) ) : ?>
							<span class="pcm-badge pcm-badge-green"><?php esc_html_e( 'Configured', 'consentguard' ); ?></span>
						<?php else : ?>
							<span class="pcm-badge pcm-badge-amber"><?php esc_html_e( 'Not set', 'consentguard' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
</div>
