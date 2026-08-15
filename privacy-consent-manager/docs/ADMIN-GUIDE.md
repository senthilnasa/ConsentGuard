# Administrator Guide

All screens live under **Privacy & Consent** in wp-admin. Only users with
`manage_options` can access them.

## Dashboard

At-a-glance health: consent record totals and accept/reject/custom split,
integration status lights, open plugin conflicts, duplicate trackers, and
current consent/policy versions. Statistics are computed from the anonymous
consent records — no extra visitor data is collected for the dashboard.

## Consent Banner

Controls everything visitors see:

- **Position/layout/animation** — bar (top/bottom), box (corners), or center modal.
- **Texts and button labels** — fully editable and translatable.
- **Colors, font size, border radius, logo** — applied via CSS variables.
- **Show Reject All** — keep this on for GDPR-style jurisdictions.
- **Close button** — off by default; enable only where a dismissal without a choice is legally acceptable.
- **Reopen button** — floating "Privacy Settings" button so visitors can change or withdraw consent at any time (a link with CSS class `pcm-open-preferences` anywhere on the page does the same).
- **Disable in WordPress Admin / Elementor Editor** — both on by default.

The banner is rendered in the visitor's browser from configuration, so the
page HTML is identical for everyone — page caches and CDNs are unaffected.

## Consent Categories

Five built-in categories (Necessary, Functional, Analytics, Marketing,
Preferences). Labels/descriptions are editable; Necessary can never be
disabled or removed. Add custom categories at the bottom of the table;
delete a custom category by clearing its label.

## Analytics

Four tabs: **Google Analytics** (GA4 + Google Consent Mode v2),
**Microsoft Clarity**, **Cloudflare Analytics**, **Google Tag Manager**.
Each integration has: enable toggle, ID/token field (validated), and consent
category. Every integration is printed in blocked form and executes only
after the visitor grants its category. GA4/Clarity/GTM also carry duplicate
guards so the same tracker never initializes twice from this plugin's side.

Cloudflare Web Analytics is cookieless; whether it needs consent is your
legal call — use its *Consent requirement* checkbox. This setting concerns
only the Web Analytics beacon, not other Cloudflare products. If Cloudflare
injects the beacon at the edge (dashboard auto-injection), disable it there;
no WordPress plugin can intercept edge-injected scripts.

## Script Manager

Add any custom snippet (e.g. Meta Pixel): name, category, position
(header/body/footer), enabled flag and the markup itself. Inline scripts,
external scripts and pixels are supported; everything is blocked until the
category is granted. Only users with `unfiltered_html` can store executable
script tags.

## Plugin Conflicts

Lists active plugins known to inject the same trackers you configured here.

- **Supported mitigation** (e.g. Site Kit Analytics): one click suppresses
  only the duplicate tag output using the other plugin's own public filters.
  Site Kit's Search Console, PageSpeed and AdSense features keep working.
- **Manual mitigation**: where no supported switch exists, the screen tells
  you exactly what to turn off in the other plugin. Nothing is ever
  deactivated automatically and no other plugin's settings are modified.
- **Ignore** hides a conflict you have assessed as acceptable.

## Cookie/Script Scanner

**Scan Now** fetches your homepage server-side and reports:

- how many live instances of each tracker exist and which plugins likely
  injected them (duplicates are flagged);
- all third-party script domains, classified via the known-domain list.
  Unknown domains are flagged — classify them to add them to the script
  blocker under that category. Unknown never means harmless.

## Jurisdiction Rules

Profiles (GDPR, UK GDPR, India DPDP, US-style opt-out, Default) control
consent behaviour per region; map countries to profiles. Each profile has a
**consent model**:

- **Opt-in** — nothing non-essential runs before the visitor agrees (GDPR-style).
- **Opt-out** — tracking is implied until the visitor objects (OneTrust-style
  US behaviour); the banner still shows, and no consent record is stored
  until the visitor makes an explicit choice.
- **Notice only** — like opt-out, but the banner offers no Reject All button
  (visitors can still opt out via Manage Preferences).

Choosing opt-out or notice-only for a region is a legal decision you make;
the plugin never defaults to implied consent. The browser **Global Privacy
Control** signal (Settings → Advanced) keeps the Marketing category denied
even under implied consent or Accept All. Geolocation uses only trusted
infrastructure headers (Cloudflare `CF-IPCountry`, GeoIP server modules) —
no external API, no stored IPs. With geolocation off or unavailable the
default profile applies, so keep the default the strictest profile you need.
The **India / DPDP Configuration** section holds notice text, purposes,
rights, contact and grievance details — all wording is yours to edit.

## Consent Records

Browsable, paginated log of consent decisions: timestamp, truncated consent
ID, action, per-category grants, versions and region. No IP addresses or
identities. Records expire automatically per the retention setting.

## Settings

Consent version (bump to re-prompt everyone), cookie lifetime, record
storage on/off, retention days, the automatic script blocker (domain
denylist with categories + allowlist that always wins), and the uninstall
data policy.

## Tools

Debug mode (browser-console `[PCM]` logging of every consent decision and
block/unblock), next scheduled cleanup time, and a JS API quick reference.
