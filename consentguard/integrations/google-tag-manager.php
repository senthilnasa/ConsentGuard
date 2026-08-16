<?php
/**
 * Generic GTM-plugin shim.
 *
 * GTM plugins (GTM4WP, Metronet Tag Manager, ...) print their container
 * from their own hooks. The automatic script blocker neutralizes
 * googletagmanager.com/gtm.js output until consent, and our own GTM
 * snippet's window.__pcmGtmLoaded guard prevents double-loading the same
 * container from our side. No unsupported internals are touched.
 *
 * @package PCM
 */

defined( 'ABSPATH' ) || exit;
