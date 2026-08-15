<?php
/**
 * Generic, plugin-agnostic mitigation helpers.
 *
 * Administrators (or site developers) can suppress a duplicate tracker that
 * is registered via wp_enqueue_script by listing its handle:
 *
 *     add_filter( 'pcm_dequeue_handles', function ( $handles ) {
 *         $handles[] = 'monsterinsights-frontend-script';
 *         return $handles;
 *     } );
 *
 * Dequeuing uses only the standard WordPress script API and is fully
 * reversible; it never edits another plugin's settings.
 *
 * @package PCM
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_enqueue_scripts',
	static function () {
		/**
		 * Filters script handles to dequeue for duplicate-tracking mitigation.
		 *
		 * @param string[] $handles Registered script handles.
		 */
		$handles = apply_filters( 'pcm_dequeue_handles', array() );

		foreach ( (array) $handles as $handle ) {
			$handle = sanitize_key( $handle );
			if ( '' !== $handle ) {
				wp_dequeue_script( $handle );
			}
		}
	},
	// Late, so the target plugins have enqueued theirs already.
	100
);
