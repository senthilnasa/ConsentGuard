# Installation

## Requirements

- WordPress 6.2 or newer (single site or multisite)
- PHP 7.4+ (tested through 8.2)
- No external services, accounts or API keys required — the plugin is fully self-hosted

## Install from a ZIP

1. Zip the `privacypress` directory (or download a release ZIP).
2. In wp-admin go to **Plugins → Add New → Upload Plugin**, choose the ZIP, click **Install Now**.
3. Click **Activate**.

## Install by copying

1. Copy the `privacypress` directory into `wp-content/plugins/`.
2. Activate it under **Plugins**.

## What activation does

- Creates the `{prefix}pcm_consents` table (consent records, no PII).
- Seeds default settings (`pcm_settings` option, autoload disabled).
- Schedules the daily `pcm_cleanup_consents` WP-Cron event for retention cleanup.

Deactivation only unschedules the cron event — **no data is ever deleted on deactivation**. Data removal happens only on uninstall, and only when *Settings → Advanced → Delete plugin data on uninstall* was enabled.

## First-run checklist

1. **PrivacyPress → Consent Banner** — set your text, labels, colors and position.
2. **PrivacyPress → Analytics** — enter your GA4 Measurement ID, Clarity Project ID, Cloudflare token and/or GTM container ID. Leave Consent Mode v2 enabled unless you have a specific reason not to.
3. **PrivacyPress → Privacy Policies** — select your Privacy Policy and Cookie Policy pages.
4. **PrivacyPress → Cookie/Script Scanner → Scan Now** — verify what actually loads on your homepage.
5. **PrivacyPress → Plugin Conflicts** — review anything flagged (e.g. Site Kit also injecting Analytics).
6. Open your site in a private window and verify:
   - the banner appears, and **no** analytics requests fire before you act;
   - after *Accept All*, GA4/Clarity/etc. requests appear;
   - after *Reject All*, nothing fires and the "Privacy Settings" reopen button shows.

## Dev setup (contributors)

```bash
composer install     # PHP dev tools (PHPUnit, PHPCS + WordPress standards)
npm install          # JS dev tools (Jest, ESLint)
composer test        # PHP unit tests
npm test             # JS tests
composer lint        # WordPress coding standards
```
