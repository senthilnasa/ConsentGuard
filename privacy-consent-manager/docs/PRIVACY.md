# Privacy Considerations

## Design principles

1. **Never assume or fake consent.** All non-necessary categories default to
   denied; Google Consent Mode defaults to denied; denied is transmitted as
   denied.
2. **Data minimization.** A consent record contains: random consent ID,
   client-generated anonymous ID, timestamps, per-category booleans,
   consent/policy versions, a region/profile code and a locale string.
   Nothing else. No IP addresses (not even hashed ones are persisted), no
   user agents, no account linkage.
3. **Self-hosted.** No SaaS backend, no phone-home, no external requests at
   runtime. The only outbound request the plugin ever makes is the
   admin-triggered loopback scan of the site's own homepage.
4. **Retention limits.** Records are deleted automatically after the
   configured retention period (default 365 days, daily WP-Cron job).
5. **Privacy-preserving geolocation.** Country comes only from headers your
   own infrastructure already adds (e.g. Cloudflare `CF-IPCountry`); it is
   used for profile selection and never stored as such.

## Visitor rights support

- **Withdrawal:** the floating "Privacy Settings" button (and the
  `pcm-open-preferences` CSS hook) reopens the preferences modal at any
  time. Withdrawal updates Consent Mode, prevents future script execution,
  clears the plugin's knowledge of granted state and removes known
  first-party tracking cookies (`_ga*`, `_gid`, `_clck`, `_clsk`, `_fbp`,
  `_gcl_*`, …). The plugin does not claim third parties erased their
  server-side data — that remains subject to each vendor's processes.
- **Erasure:** a WordPress personal-data eraser is registered; providing the
  anonymous consent ID (shown to the visitor in the preferences modal)
  deletes the matching records. Records auto-expire regardless.
- **Transparency:** suggested privacy-policy text is registered with
  WordPress' policy guide (Settings → Privacy).

## What this plugin does NOT do

- It does not make your site legally compliant by itself. It provides the
  technical mechanisms; the legal configuration, policy texts and vendor
  assessments are the site owner's responsibility.
- It cannot control scripts injected outside WordPress (e.g. Cloudflare
  edge-injected beacons, tags added at the CDN level).
- It cannot force tags inside a GTM container to respect consent; it
  forwards Consent Mode signals and warns administrators accordingly.
