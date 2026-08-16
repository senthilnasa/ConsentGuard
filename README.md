# PrivacyPress

[![CI](https://github.com/senthilnasa/PrivacyPress/actions/workflows/ci.yml/badge.svg)](https://github.com/senthilnasa/PrivacyPress/actions/workflows/ci.yml)
[![License: GPL v2](https://img.shields.io/badge/License-GPL_v2-blue.svg)](https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777BB4.svg)](https://www.php.net/)
[![WordPress 6.2+](https://img.shields.io/badge/WordPress-6.2%2B-21759B.svg)](https://wordpress.org/)

A free, open-source, self-hosted **privacy, consent, analytics and tracking
management platform for WordPress** — a community alternative to hosted CMPs
like OneTrust and CookieYes, with no SaaS account, no external server and no
vendor lock-in.

> **One website → one centralized consent decision → one source of truth for
> analytics and tracking.**

The plugin lives in [`privacypress/`](privacypress/).

## Highlights

- 🎨 **Modern consent banner & preferences center** — CookieYes-style detail
  modal with accordion categories, per-cookie tables (Cookie / Duration /
  Description), expandable descriptions; bar/box/center layouts,
  light/dark/auto themes, animations, WCAG-minded (focus trap, ARIA dialogs,
  live-region announcements, keyboard support)
- 🧲 **Floating revisit widget** — draggable icon button with custom logo,
  four default corners, position remembered per visitor
- 🧩 **Managed integrations** — Google Analytics 4, Google Consent Mode v2,
  Microsoft Clarity, Cloudflare Web Analytics, Google Tag Manager, plus any
  custom script (Meta Pixel etc.)
- 🖼️ **Embed blocking with placeholders** — YouTube, Vimeo, Spotify,
  Facebook and other iframes stay blocked behind an "Accept & load" card
  until their category is granted
- 🌍 **Region-aware, OneTrust-style** — jurisdiction profiles (GDPR,
  UK GDPR, India DPDP, US-style opt-out), country → profile rules, opt-in /
  opt-out / notice-only consent models, privacy-preserving geolocation from
  infrastructure headers only
- 🛡️ **Global Privacy Control** — the browser GPC signal keeps the
  Marketing category denied on blanket accepts (on by default)
- 🚫 **Automatic script blocking** — third-party trackers injected by themes
  or other plugins are held inert until consent; configurable deny/allow
  lists; cache- and CDN-safe by design
- 🔍 **Duplicate tracking protection** — detects Site Kit, the Clarity
  plugin, Cloudflare, MonsterInsights, GTM4WP, PixelYourSite and more;
  one-click safe mitigation via the other plugin's own supported hooks;
  never deactivates anything
- 🍪 **Cookie discovery** — unknown cookies/localStorage keys observed while
  an administrator browses the site are queued for one-click classification
  into the inventory
- 📊 **Consent records & analytics** — anonymous (no IPs), versioned,
  retained per your policy, 30-day trend chart, CSV export, per-ID erasure,
  branded **proof-of-consent PDF** per record, WordPress privacy-tools
  integration
- 🌐 **Multilingual** — per-locale text overrides built in; `wpml-config.xml`
  for WPML/Polylang String Translation; POT included (English/Tamil/Hindi or
  any locale)
- 🧱 **Builder-friendly** — Gutenberg blocks (Privacy Settings button,
  live Cookie Details table), Elementor widget, `[pcm_privacy_settings]`
  shortcode
- 🔗 **Interoperable** — WP Consent API bridge, JavaScript API, DOM events,
  PHP hooks, REST API, WP-CLI commands (`wp privacypress …`),
  settings import/export (JSON), multisite-aware

## Quick start

```bash
git clone https://github.com/senthilnasa/PrivacyPress.git
cp -r PrivacyPress/privacypress wp-content/plugins/
```

Or download the [latest release ZIP](https://github.com/senthilnasa/PrivacyPress/releases)
and install via *Plugins → Add New → Upload Plugin*.

1. Activate and configure under **PrivacyPress** in wp-admin.
2. Add your GA4 / Clarity / Cloudflare / GTM IDs under **Analytics**.
3. Run **Cookie/Script Scanner → Scan Now** and review **Plugin Conflicts**.
4. Full guides: [Installation](privacypress/docs/INSTALL.md) ·
   [Admin Guide](privacypress/docs/ADMIN-GUIDE.md) ·
   [Developer Docs](privacypress/docs/DEVELOPER.md)

## Local development & testing

```bash
git clone https://github.com/senthilnasa/PrivacyPress.git && cd PrivacyPress
docker compose up -d          # WordPress + MariaDB on http://localhost:9080
cd privacypress
composer install && npm install
composer test                 # PHPUnit
npm test                      # Jest
composer lint                 # WordPress coding standards
```

## Contributing

Issues and pull requests are welcome at
[github.com/senthilnasa/PrivacyPress](https://github.com/senthilnasa/PrivacyPress) —
see [CONTRIBUTING.md](CONTRIBUTING.md). Security reports: see
[SECURITY.md](SECURITY.md).

## Legal note

This plugin provides technical consent-management features but does not
constitute legal advice or guarantee compliance with GDPR, ePrivacy, the
DPDP Act, CCPA/CPRA or any other law. Configuration according to your actual
data-processing activities is your responsibility.

## License

[GPL-2.0-or-later](LICENSE) — free as in freedom, forever.
