# Privacy & Consent Manager

A free, open-source, self-hosted **privacy, consent, analytics and tracking
management platform for WordPress** — a community alternative to hosted CMPs
like OneTrust and CookieYes, with no SaaS account, no external server and no
vendor lock-in.

> **One website → one centralized consent decision → one source of truth for
> analytics and tracking.**

The plugin lives in [`privacy-consent-manager/`](privacy-consent-manager/).

## Highlights

- 🎨 **Modern consent banner** — bar/box/center layouts, light/dark/auto
  themes, full text/color/typography/logo customization, smooth animations,
  WCAG-minded (focus trap, ARIA dialogs, keyboard support)
- 🧩 **Managed integrations** — Google Analytics 4, Google Consent Mode v2,
  Microsoft Clarity, Cloudflare Web Analytics, Google Tag Manager, plus any
  custom script (Meta Pixel etc.)
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
- 📊 **Consent records** — anonymous (no IPs), versioned, retained per your
  policy, CSV export, per-ID erasure, WordPress privacy-tools integration
- 🔗 **Interoperable** — WP Consent API bridge, JavaScript API, DOM events,
  PHP hooks, REST API
- 🌐 **Translation-ready** — POT included; English/Tamil/Hindi and any other
  locale via standard WordPress translations

## Quick start

1. Copy `privacy-consent-manager/` into `wp-content/plugins/` and activate.
2. Configure under **Privacy & Consent** in wp-admin.
3. Full guides: [Installation](privacy-consent-manager/docs/INSTALL.md) ·
   [Admin Guide](privacy-consent-manager/docs/ADMIN-GUIDE.md) ·
   [Developer Docs](privacy-consent-manager/docs/DEVELOPER.md)

## Development

```bash
cd privacy-consent-manager
composer install && npm install
composer test    # PHPUnit
npm test         # Jest
composer lint    # WordPress coding standards
```

See [CONTRIBUTING.md](CONTRIBUTING.md). Security policy and privacy design:
[SECURITY.md](privacy-consent-manager/docs/SECURITY.md) ·
[PRIVACY.md](privacy-consent-manager/docs/PRIVACY.md).

## Legal note

This plugin provides technical consent-management features but does not
constitute legal advice or guarantee compliance with GDPR, ePrivacy, the
DPDP Act, CCPA/CPRA or any other law. Configuration according to your actual
data-processing activities is your responsibility.

## License

[GPL-2.0-or-later](LICENSE).
