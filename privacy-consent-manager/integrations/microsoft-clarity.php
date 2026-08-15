<?php
/**
 * Microsoft Clarity plugin shim.
 *
 * The official Clarity plugin has no supported server-side switch we could
 * flip, so there is no automatic mitigation (the conflict UI says so).
 * Two safety nets still prevent duplicate tracking:
 *
 * 1. The automatic script blocker neutralizes any clarity.ms script emitted
 *    by other plugins until the visitor grants the analytics category.
 * 2. Our own Clarity snippet refuses to initialize when window.clarity
 *    already exists.
 *
 * @package PCM
 */

defined( 'ABSPATH' ) || exit;
