<?php
/**
 * Cloudflare plugin shim.
 *
 * The Cloudflare WordPress plugin manages CDN/firewall features; Web
 * Analytics auto-injection is typically configured on the Cloudflare
 * dashboard (edge injection), which no WordPress plugin can suppress.
 * There is therefore no supported automatic mitigation — the conflict UI
 * instructs the administrator to disable either the edge injection or this
 * plugin's Cloudflare integration. We deliberately do NOT auto-block
 * static.cloudflareinsights.com beacons injected at the edge... they never
 * pass through PHP output buffering anyway (added after our HTML leaves
 * the origin), which is exactly why this must be fixed at the source.
 *
 * @package PCM
 */

defined( 'ABSPATH' ) || exit;
