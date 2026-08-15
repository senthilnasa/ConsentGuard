# Changelog

All notable changes to Privacy & Consent Manager.

## [1.0.0] — 2026-08-15

### Added
- Consent banner (bar/box/center layouts, positions, animations, full text/color/typography customization, logo, reopen button) rendered client-side for cache safety.
- Preferences modal with granular per-category toggles, descriptions, focus trap, ESC handling, ARIA dialog semantics and visible consent ID.
- Five built-in consent categories (Necessary, Functional, Analytics, Marketing, Preferences) plus administrator-defined custom categories.
- Anonymous consent records (no IPs) with consent/policy versioning, configurable retention and daily WP-Cron cleanup; WordPress personal-data eraser integration.
- Google Analytics 4 integration (gtag.js) with validated Measurement IDs, consent gating and double-init guard.
- Google Consent Mode v2: denied-by-default signals printed before any Google tag, ads_data_redaction / url_passthrough options, real-state updates on consent change.
- Microsoft Clarity integration with duplicate-injection guard.
- Cloudflare Web Analytics integration with administrator-controlled consent requirement (cookieless beacon), clearly scoped to Web Analytics only.
- Google Tag Manager integration with consent gating and an explicit warning about tags inside the container.
- Custom Script Manager (header/body/footer, inline/external, capability-gated storage).
- Automatic script-blocking engine: output-buffer rewriting of known third-party tracking scripts into inert templates, configurable domain→category denylist, allowlist that always wins, JSON-LD/same-site/managed-template safeguards, fail-open design.
- Plugin Conflict Manager: detection registry for Site Kit, Clarity, Cloudflare, MonsterInsights, GA Google Analytics, Analytify, CAOS, GTM4WP, Metronet, Meta Pixel, PixelYourSite; safe mitigation via Site Kit's supported tag-blocking filters; ignore list; no automatic plugin deactivation ever.
- Duplicate Tracking Detector: on-demand loopback homepage scan counting live tracker instances, attributing sources and listing/classifying third-party script domains (unknown flagged, classifiable into the blocker).
- Jurisdiction profiles (GDPR, UK GDPR, India DPDP, default) with country rules, header-based privacy-preserving geolocation and a fully editable DPDP configuration section.
- Analytics health dashboard (consent stats, integration status, conflicts, privacy status).
- Cookie policy draft generator with mandatory legal-review disclaimer.
- JavaScript consent API (`PrivacyConsent.*`) with DOM events; PHP actions/filters; REST API (`pcm/v1`) with capability checks and a rate-limited public consent endpoint.
- Debug mode with `[PCM]` console logging.
- Elementor editor/admin suppression options; translation-ready (POT included).
- PHPUnit unit suite (53 tests) and Jest suite (15 tests); PHPCS (WordPress standards) configuration.

### Security
- Nonces on all admin actions, capability checks everywhere, `unfiltered_html` gate for executable scripts, strict input validation, prepared statements, directory index guards.
