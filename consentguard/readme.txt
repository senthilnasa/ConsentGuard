=== ConsentGuard ===
Contributors: senthilnasa
Plugin URI: https://github.com/senthilnasa/ConsentGuard
Tags: consent, gdpr, dpdp, cookie banner, analytics
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Centralized privacy, consent, analytics and tracking management. One consent decision controls GA4, Consent Mode v2, Clarity, Cloudflare Analytics, GTM and custom scripts — and duplicate tracking from other plugins is detected.

== Description ==

ConsentGuard turns your WordPress site into a single, centralized privacy layer:

**One website → one centralized consent decision → one source of truth for analytics and tracking.**

= Consent management =

* Modern, accessible, fully responsive consent banner (customizable position, layout, labels, colors, logo, animation)
* Preferences modal with granular categories: Necessary, Functional, Analytics, Marketing, Preferences — plus your own custom categories
* Accept All / Reject All / Save Preferences / consent withdrawal via a floating "Privacy Settings" button
* Consent versioning: bump the version and visitors are re-prompted
* Privacy-conscious consent records: anonymous ID, timestamp, versions, categories, region code, language — **no IP addresses**
* Configurable retention with automatic daily cleanup (WP-Cron)

= Managed integrations =

* Google Analytics 4 (gtag.js) — loads only after consent, never twice
* Google Consent Mode v2 — all seven signals default to denied; updates mirror the real visitor choice
* Microsoft Clarity — consent-gated, duplicate-guarded
* Cloudflare Web Analytics — consent requirement is your legal decision (the beacon is cookieless); clearly separated from other Cloudflare products
* Google Tag Manager — consent-gated, with a clear warning that tags inside GTM must also respect consent
* Custom scripts (e.g. Meta Pixel) — header/body/footer, inline or external, all consent-blocked until granted

= Script blocking engine =

* Automatically rewrites known third-party tracking scripts emitted by themes or other plugins into inert form until consent is granted
* Configurable domain denylist with per-domain categories, plus an allowlist that always wins
* Cache-safe by design: pages remain identical for all visitors, so page caches and CDNs are unaffected

= Duplicate tracking protection =

* Detects active tracking plugins (Site Kit, Microsoft Clarity, Cloudflare, MonsterInsights, GTM4WP, PixelYourSite and more)
* "Scan Now" homepage scanner counts real tracker instances and attributes them to their sources
* Safe conflict resolution: never deactivates another plugin, never edits another plugin's settings. Site Kit's Analytics output is suppressed via Site Kit's own supported filters; where no supported mechanism exists, the plugin tells you exactly what to change manually

= Jurisdictions =

* Configurable consent profiles (GDPR, UK GDPR, India DPDP, default) and country → profile rules
* Privacy-preserving geolocation from trusted infrastructure headers only — no external API, no stored IPs
* Dedicated India/DPDP configuration: notice text, purposes, rights, contact and grievance details — all wording editable

= For developers =

* JavaScript API: `PrivacyConsent.getConsent()`, `hasConsent()`, `onChange()` plus DOM events
* PHP hooks: `pcm_consent_changed`, `pcm_should_load_script`, `pcm_autoblock_category` and more
* REST API with strict capability checks
* No SaaS, no external server, no vendor lock-in — everything runs inside WordPress

**Legal note:** this plugin provides technical consent-management features but does not constitute legal advice or guarantee compliance with GDPR, ePrivacy, the DPDP Act or other laws. You are responsible for configuring it according to your actual data-processing activities.

== Installation ==

1. Upload the `ConsentGuard` folder to `/wp-content/plugins/`, or install via Plugins → Add New.
2. Activate the plugin.
3. Go to **ConsentGuard → Consent Banner** and configure text and design.
4. Add your IDs under **ConsentGuard → Analytics** (GA4 Measurement ID, Clarity Project ID, Cloudflare token, GTM container).
5. Run **ConsentGuard → Cookie/Script Scanner → Scan Now** and review conflicts under **Plugin Conflicts**.
6. Select your Privacy Policy and Cookie Policy pages under **Privacy Policies**.

== External services ==

ConsentGuard makes **no external requests by itself**. Third-party services load only when BOTH conditions are met: the administrator explicitly configured them AND the visitor granted the matching consent category.

* **Google Analytics 4 / Google Tag Manager / Google Consent Mode** (Google LLC) — loaded from googletagmanager.com only after analytics/marketing consent, to measure site usage as configured by the site owner. [Terms](https://policies.google.com/terms), [Privacy](https://policies.google.com/privacy)
* **Microsoft Clarity** (Microsoft Corporation) — loaded from clarity.ms only after analytics consent, for session analytics. [Terms](https://www.microsoft.com/en-us/legal/terms-of-use), [Privacy](https://privacy.microsoft.com/privacystatement)
* **Cloudflare Web Analytics** (Cloudflare, Inc.) — loaded from static.cloudflareinsights.com when configured; consent requirement is an administrator setting because the beacon is cookieless. [Terms](https://www.cloudflare.com/website-terms/), [Privacy](https://www.cloudflare.com/privacypolicy/)
* Custom scripts and embeds configured by the administrator load only after their assigned consent category is granted.

The only request the plugin itself initiates is an administrator-triggered loopback scan of the site's **own homepage** (Cookie/Script Scanner → Scan Now). Consent records are stored in the site's own database; nothing is transmitted to the plugin authors or any external service.

== Frequently Asked Questions ==

= Does this plugin make my site GDPR/DPDP compliant? =

No plugin can guarantee legal compliance. This plugin provides the technical mechanisms (consent before tracking, granular categories, withdrawal, records, blocking); the legal assessment and configuration are yours.

= Does it work with page caching and CDNs? =

Yes. Consent is evaluated in the visitor's browser from a first-party cookie, and all pages are identical for every visitor, so WP Rocket, LiteSpeed Cache, W3 Total Cache and Cloudflare caching work unchanged.

= Will it deactivate Site Kit or other plugins? =

Never. Conflicts are reported, and only the specific duplicate tracking output is suppressed — using the other plugin's own supported hooks. Site Kit's Search Console, PageSpeed and AdSense features keep working.

= Where are consent records stored? =

In your own WordPress database (an anonymized custom table). Nothing is sent to any external service.

== Changelog ==

= 1.0.0 =
* Initial release: consent banner and preferences modal, five consent categories plus custom categories, GA4, Google Consent Mode v2, Microsoft Clarity, Cloudflare Web Analytics, GTM, custom script manager, automatic script blocker, plugin conflict manager, duplicate tracking scanner, jurisdiction profiles (GDPR/UK GDPR/DPDP), consent records with retention, cookie policy generator, debug mode, REST API and JavaScript consent API.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
