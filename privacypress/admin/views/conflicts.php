<?php
/**
 * Plugin Conflicts view.
 *
 * @package PCM
 * @var array                       $settings
 * @var PCM\Plugin_Conflict_Manager $conflicts
 */

defined( 'ABSPATH' ) || exit;

use PCM\Admin\Admin;

$pcm_conflicts = $conflicts->get_conflicts();

/**
 * Builds a nonce-protected conflict action URL.
 *
 * @param string $id Conflict id.
 * @param string $op Operation.
 * @return string
 */
$pcm_action_url = static function ( $id, $op ) {
	return wp_nonce_url(
		admin_url( 'admin-post.php?action=pcm_conflict&id=' . rawurlencode( $id ) . '&op=' . rawurlencode( $op ) ),
		'pcm_conflict',
		'_pcm_nonce'
	);
};

Admin::maybe_notice();
?>
<h1><?php esc_html_e( 'Plugin Conflicts', 'privacypress' ); ?></h1>
<p><?php esc_html_e( 'Plugins below may inject the same trackers this plugin manages, which can cause duplicate data collection. Conflicts are never resolved by deactivating a plugin — only the specific tracking output is suppressed, and only where the other plugin provides a supported way to do so.', 'privacypress' ); ?></p>

<?php if ( empty( $pcm_conflicts ) ) : ?>
	<div class="notice notice-success inline"><p><?php esc_html_e( 'No potential tracking conflicts detected.', 'privacypress' ); ?></p></div>
<?php endif; ?>

<?php foreach ( $pcm_conflicts as $pcm_conflict ) : ?>
	<div class="pcm-card <?php echo $pcm_conflict['ignored'] ? 'pcm-muted' : ''; ?>">
		<h2>
			<?php echo $pcm_conflict['mitigated'] ? '✅' : ( $pcm_conflict['ignored'] ? '➖' : '⚠' ); ?>
			<?php esc_html_e( 'Potential Tracking Conflict', 'privacypress' ); ?> — <?php echo esc_html( $pcm_conflict['plugin_name'] ); ?>
		</h2>
		<p>
			<?php
			printf(
				/* translators: 1: service name, 2: plugin name */
				esc_html__( '%1$s may be injected by %2$s. PrivacyPress is also configured to manage %1$s.', 'privacypress' ),
				'<strong>' . esc_html( $pcm_conflict['service_label'] ) . '</strong>',
				esc_html( $pcm_conflict['plugin_name'] )
			);
			?>
		</p>
		<p class="description"><?php echo esc_html( $pcm_conflict['note'] ); ?></p>
		<p>
			<?php if ( ! $pcm_conflict['mitigated'] && 'supported' === $pcm_conflict['mitigation'] ) : ?>
				<a class="button button-primary" href="<?php echo esc_url( $pcm_action_url( $pcm_conflict['id'], 'mitigate' ) ); ?>">
					<?php
					printf(
						/* translators: 1: plugin name, 2: service name */
						esc_html__( 'Disable %2$s output from %1$s', 'privacypress' ),
						esc_html( $pcm_conflict['plugin_name'] ),
						esc_html( $pcm_conflict['service_label'] )
					);
					?>
				</a>
			<?php elseif ( $pcm_conflict['mitigated'] ) : ?>
				<a class="button" href="<?php echo esc_url( $pcm_action_url( $pcm_conflict['id'], 'unmitigate' ) ); ?>"><?php esc_html_e( 'Re-enable', 'privacypress' ); ?></a>
			<?php endif; ?>

			<?php if ( $pcm_conflict['ignored'] ) : ?>
				<a class="button" href="<?php echo esc_url( $pcm_action_url( $pcm_conflict['id'], 'unignore' ) ); ?>"><?php esc_html_e( 'Stop ignoring', 'privacypress' ); ?></a>
			<?php else : ?>
				<a class="button" href="<?php echo esc_url( $pcm_action_url( $pcm_conflict['id'], 'ignore' ) ); ?>"><?php esc_html_e( 'Ignore', 'privacypress' ); ?></a>
			<?php endif; ?>
		</p>
	</div>
<?php endforeach; ?>
