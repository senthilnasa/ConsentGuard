# Security Documentation

## Threat model & controls

| Threat | Control |
|--------|---------|
| CSRF on admin actions | Every settings form and admin-post action carries a nonce (`pcm_save_settings`, `pcm_conflict`, `pcm_scan`, `pcm_generate_policy`) verified via `check_admin_referer()`; AJAX uses `check_ajax_referer()` |
| Privilege escalation | All admin pages, REST admin endpoints and actions require `manage_options`; storing executable `<script>` markup additionally requires `unfiltered_html` — lower-capability input is reduced with `wp_kses_post()` |
| XSS (stored) | Every setting is sanitized on write in `Settings::sanitize()` (whitelisted enums, `sanitize_text_field`, `sanitize_hex_color`, validated ID formats like `^G-[A-Z0-9]{4,16}$`) and escaped on output (`esc_html`, `esc_attr`, `esc_url`, `esc_textarea`) |
| XSS (reflected) | Admin notices use fixed message keys, never echoed request input |
| SQL injection | The only custom table is accessed through `$wpdb->prepare()`; table name is built from `$wpdb->prefix` only |
| Unauthorized REST access | `permission_callback` on every route; admin routes return 401/403 via `rest_authorization_required_code()`; consent records are never exposed publicly |
| Abuse of the public consent endpoint | Strict payload validation (fixed keys, UUID check, bounded lengths, enum action), no PII accepted or stored, per-client rate limit (20/min via salted, expiring IP hash) |
| Arbitrary script injection via custom scripts | Capability-gated as above; scripts are stored verbatim only for `unfiltered_html` users (same trust boundary as the WordPress Custom HTML widget) |
| Direct file access | Every PHP file starts with `defined( 'ABSPATH' ) \|\| exit;` and every directory ships an `index.php` guard |
| Supply chain | Zero runtime dependencies; no external requests at runtime except the admin-triggered loopback scan of the site's own homepage |

## Data security

- Consent records contain no IPs, user agents, emails or user IDs.
- The rate limiter hashes IPs with `wp_salt('nonce')` HMAC and keeps them
  only in 60-second transients.
- Options are stored with autoload disabled where large (`pcm_settings`).

## Reporting

Report vulnerabilities privately to the maintainers; do not open public
issues for security reports.
