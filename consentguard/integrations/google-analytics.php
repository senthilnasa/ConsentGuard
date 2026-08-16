<?php
/**
 * Generic GA-plugin shim.
 *
 * Third-party GA plugins (MonsterInsights, GA Google Analytics, Analytify,
 * CAOS, ...) have no common supported off-switch, so no automatic
 * mitigation is offered. Duplicate protection comes from:
 *
 * 1. The automatic script blocker (google-analytics.com /
 *    googletagmanager.com domains + inline gtag signatures) which holds
 *    their output until consent.
 * 2. Our GA4 snippet's window.__pcmGa4Loaded guard, which prevents a second
 *    initialization from this plugin's side.
 *
 * Where one of these plugins registers its script through
 * wp_enqueue_script, administrators can also dequeue it with the standard
 * `pcm_dequeue_handles` filter handled in generic.php.
 *
 * @package PCM
 */

defined( 'ABSPATH' ) || exit;
