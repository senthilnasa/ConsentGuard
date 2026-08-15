# Developer Documentation

## Architecture

```text
privacy-consent-manager.php     bootstrap, autoloader, activation hooks
includes/
  class-plugin.php              module orchestrator (single wiring point)
  class-settings.php            settings repository + all sanitization
  class-consent-manager.php     consent domain logic + client config
  class-consent-storage.php     custom-table persistence + retention
  class-script-manager.php      gate + blocked-template rendering helpers
  class-script-blocker.php      output-buffer auto-blocker for 3rd-party tags
  class-analytics-manager.php   coordinates the built-in integrations
  class-google-analytics.php    GA4 (gtag.js)
  class-google-consent-mode.php Consent Mode v2 defaults + signal map
  class-microsoft-clarity.php   Clarity
  class-cloudflare-analytics.php Cloudflare Web Analytics beacon
  class-google-tag-manager.php  GTM container
  class-custom-script-manager.php admin-defined scripts
  class-plugin-detector.php     read-only detection of tracking plugins
  class-plugin-conflict-manager.php conflict list + safe mitigations
  class-duplicate-tracking-detector.php on-demand homepage scanner
  class-geolocation-manager.php header-based jurisdiction resolution
  class-privacy-manager.php     WP privacy tools (eraser), legal notice
  class-policy-manager.php      cookie-policy draft generator
  class-rest-api.php            pcm/v1 endpoints
  class-security.php            capability constants, admin nonces
admin/                          menu, form handling, views
public/                         frontend class + JS/CSS
integrations/                   per-plugin mitigation shims (Site Kit etc.)
```

**Key invariant:** managed and auto-blocked scripts are printed as
`<script type="text/plain" data-pcm-category="...">`. The page is therefore
identical for every visitor (cache/CDN-safe) and the browser-side unblocker
(`public/js/script-blocker.js`) executes each template exactly once when its
category is granted. Consent is never evaluated per-request in PHP for
frontend output.

## Consent flow

1. `Google_Consent_Mode::print_defaults()` prints denied defaults at `wp_head` priority 1.
2. Integrations print blocked templates (`wp_head` 20 / `wp_body_open`).
3. `consent-manager.js` loads config from `window.PCMConfig`, reads the
   first-party cookie `pcm_consent`, renders the banner/modal if needed.
4. On decision: cookie written → Consent Mode `update` pushed → blocked
   scripts for granted categories executed → denied-category first-party
   cookies cleared → events fired → anonymous record POSTed to REST.
5. Withdrawal of a previously granted category reloads the page so
   already-running trackers stop.

## Database schema

Table `{$wpdb->prefix}pcm_consents`:

| Column            | Type                 | Notes                              |
|-------------------|----------------------|------------------------------------|
| id                | BIGINT UNSIGNED PK   | auto increment                     |
| consent_id        | VARCHAR(36), indexed | UUID per decision                  |
| anonymous_id      | VARCHAR(36), indexed | client-generated UUID per browser  |
| consent_version   | VARCHAR(20), indexed |                                    |
| policy_version    | VARCHAR(20)          |                                    |
| necessary…preferences | TINYINT(1)       | one column per built-in category   |
| extra_categories  | TEXT (JSON)          | custom categories                  |
| region            | VARCHAR(8)           | profile key or country code        |
| language          | VARCHAR(12)          | e.g. en_US                         |
| action            | VARCHAR(20)          | accept_all/reject_all/custom/withdraw/update |
| created_at        | DATETIME, indexed    | UTC                                |

No IP addresses, user agents or user IDs — by design.

## JavaScript API

```js
PrivacyConsent.getConsent();            // {analytics: true, ...} or null
PrivacyConsent.hasConsent('analytics'); // boolean (required categories always true)
var off = PrivacyConsent.onChange(cb);  // cb(consent); returns unsubscribe fn
PrivacyConsent.acceptAll();
PrivacyConsent.rejectAll();
PrivacyConsent.withdraw();
PrivacyConsent.openPreferences();
PrivacyConsent.getAnonymousId();        // UUID or null
```

DOM events on `document` (detail = consent map):
`privacy_consent_ready`, `privacy_consent_changed`,
`privacy_analytics_granted`, `privacy_analytics_denied`,
`privacy_marketing_granted`, `privacy_marketing_denied`.

Example:

```js
document.addEventListener('privacy_consent_ready', function () {
  if (PrivacyConsent.hasConsent('analytics')) {
    // initialize your own analytics
  }
});
```

Any element with class `pcm-open-preferences` opens the preferences modal.

## PHP hooks

### Actions

| Hook | Args | Fired when |
|------|------|------------|
| `pcm_loaded` | `Plugin $plugin` | all modules registered |
| `pcm_consent_recorded` | `array $record` | a consent record was stored |
| `pcm_consent_changed` | `array $consent` (slug => bool) | consent changed |
| `pcm_consents_cleaned` | `int $deleted` | retention cleanup ran |
| `pcm_scan_completed` | `array $scan` | homepage scan finished |
| `pcm_conflict_mitigated` | `array $conflict` | a mitigation was enabled |

### Filters

| Hook | Signature | Purpose |
|------|-----------|---------|
| `pcm_should_load_script` | `(bool $configured, string $id, string $category)` | veto any managed script's (blocked) output |
| `pcm_should_render_banner` | `(bool $should)` | suppress the banner per request |
| `pcm_consent_categories` | `(array $categories)` | add/alter categories programmatically |
| `pcm_consent_mode_map` | `(array $map)` | remap Consent Mode signals to categories |
| `pcm_autoblock_category` | `(string $category, string $src, string $body)` | override the auto-blocker's decision ('' = don't block) |
| `pcm_script_blocker_enabled` | `(bool $enabled)` | disable the output buffer per request |
| `pcm_custom_scripts` | `(array $scripts)` | filter custom script definitions |
| `pcm_known_tracking_plugins` | `(array $registry)` | extend conflict detection |
| `pcm_conflict_mitigation_supported` | `(bool $supported, string $slug, string $service)` | declare a supported mitigation |
| `pcm_plugin_conflicts` | `(array $conflicts)` | filter computed conflicts |
| `pcm_dequeue_handles` | `(string[] $handles)` | dequeue duplicate trackers registered via wp_enqueue_script |
| `pcm_geo_headers` | `(string[] $headers)` | change trusted country headers |
| `pcm_resolved_profile` | `(array $resolved, string $country)` | override jurisdiction resolution |
| `pcm_generated_cookie_policy` | `(string $html, array $services)` | filter the policy draft |

Example — force your own tracker into the analytics category:

```php
add_filter( 'pcm_autoblock_category', function ( $category, $src ) {
    if ( false !== strpos( (string) $src, 'metrics.example-cdn.com' ) ) {
        return 'analytics';
    }
    return $category;
}, 10, 2 );
```

## REST API (`/wp-json/pcm/v1`)

| Route | Method | Auth | Purpose |
|-------|--------|------|---------|
| `/consent` | POST | public, rate-limited | record a consent decision (fixed shape, no PII accepted) |
| `/settings` | GET/POST | `manage_options` | read/update settings |
| `/scan` | POST | `manage_options` | run the duplicate-tracking scan |
| `/records` | GET | `manage_options` | paginated consent records |
| `/conflicts/{id}` | POST | `manage_options` | mitigate/unmitigate/ignore/unignore |

The public `/consent` endpoint deliberately takes no nonce (it is called
from fully cached pages where nonces go stale); it accepts only a strict
validated payload, stores no PII and is rate-limited per salted IP hash
(the hash lives in a 60-second transient and is never persisted).

## Server-side consent checks

On uncached requests (logged-in users, AJAX) you can ask PHP:

```php
$consent = pcm()->module( 'consent' );
if ( $consent->has_consent( 'analytics' ) ) { /* ... */ }
```

Never use this to vary cached frontend HTML — that breaks cache correctness;
use the JS API instead.
