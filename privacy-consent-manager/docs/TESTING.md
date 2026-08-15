# Testing

## Automated tests

### PHP unit tests (no WordPress install needed)

```bash
composer install
composer test        # or: vendor/bin/phpunit
```

The suite (`tests/php`, 53 tests) runs against lightweight WordPress
function shims (`tests/php/bootstrap.php`) and covers:

- **Consent:** no consent → all optional categories denied; analytics
  accepted/denied; marketing accepted/denied; reject-all keeps only
  Necessary; consent-version change invalidates stored consent; malformed
  cookies ignored; record sanitization (unknown categories dropped,
  Necessary forced on, UUID validation, bounded fields, action whitelist).
- **Script blocking:** GA4/Clarity/Meta domains categorized; subdomain
  matching; same-site and unknown domains never blocked; allowlist wins;
  admin scanner classifications block; external + inline neutralization;
  JSON-LD, managed templates and non-HTML payloads untouched; veto filter.
- **Settings/security:** section whitelist, ID format validation (a script
  payload in a measurement ID is rejected), color fallbacks, category
  invariants, retention bounds.
- **Jurisdictions:** header detection (incl. XX placeholder), IN→DPDP,
  EU→GDPR, GB→UK GDPR, unknown→default profile.

### JavaScript tests (Jest + jsdom)

```bash
npm install
npm test
```

15 tests covering the real frontend bundle (jsdom executes the unblocked
scripts, so consent-gated execution is verified end-to-end):

- default state, acceptAll/rejectAll, persistence across page loads,
  version-change re-prompt, onChange listeners, DOM events;
- blocked inline/external scripts execute only after the right category is
  granted, attributes are restored, and never execute twice;
- Google Consent Mode updates (denied stays denied);
- accessibility (dialog semantics, labelled toggles, Escape handling).

## Integration testing against a real WordPress

Use [wp-env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/):

```bash
npm -g i @wordpress/env
wp-env start   # from the plugin directory; it maps the plugin automatically
```

Then verify manually (checklist):

| Area | Check |
|------|-------|
| Consent | Banner shows in a private window; no analytics requests in DevTools → Network before a choice |
| GA4 | After Accept All, `gtag/js?id=G-…` and `collect` requests appear; after Reject All they never do |
| Clarity | Same for `clarity.ms`; with the official Clarity plugin active, only one tracker initializes |
| GTM | Container loads only after consent; Consent Mode defaults precede it |
| Conflicts | Activate Site Kit with Analytics → conflict appears; mitigation suppresses only the Site Kit tag |
| Scanner | Scan Now counts instances correctly and flags unknown third-party domains |
| Withdrawal | Revoking analytics reloads the page and `_ga*` cookies are gone |
| Caching | With WP Rocket/W3TC/LiteSpeed page cache on, two different browsers each get their own banner state |
| Elementor | Frontend widgets work; no banner inside the Elementor editor |
| REST security | `GET /wp-json/pcm/v1/records` as anonymous returns 401; as admin returns data |
| Uninstall | With "delete data" off, table and options survive uninstall; with it on, they are removed |

## Security test notes

- Try saving `"><script>alert(1)</script>` as a GA4 ID, banner title, domain
  entry and category label — it must be rejected or neutralized everywhere.
- POST malformed payloads to `/pcm/v1/consent` (missing categories, bogus
  UUID, unknown action, oversized region) — each returns 400.
- Replay `/pcm/v1/consent` >20×/min from one IP — returns 429.
- Call admin endpoints without `X-WP-Nonce`/cookie — 401/403.
