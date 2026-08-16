# Contributing

Thanks for helping make consent management better for the WordPress
community! Contributions of all kinds are welcome: bug reports, docs,
translations, tracker-detection rules and code.

- Repository: https://github.com/senthilnasa/ConsentGuard
- Bug reports & feature requests: https://github.com/senthilnasa/ConsentGuard/issues
- Security issues: see [SECURITY.md](SECURITY.md) — never a public issue.

## Ground rules

- **Privacy first.** PRs must never add IP/PII collection, external
  phone-home calls, or consent assumptions. "Denied" must always mean
  denied.
- **Never break other plugins.** Conflict mitigations may only use the other
  plugin's publicly supported hooks or standard WordPress APIs — no database
  writes into another plugin's settings, no automatic deactivation.
- **Cache safety.** Frontend output must be identical for every visitor;
  consent decisions belong in the browser.

## Workflow

1. Fork and branch from `master`.
2. `composer install && npm install` (from the repo root — tooling lives there; the plugin itself is `consentguard/`)
3. Make your change, with tests:
   - PHP: `composer test` (PHPUnit, `tests/php`)
   - JS: `npm test` (Jest, `tests/js`)
   - Style: `composer lint` (WordPress coding standards)
4. Open a PR describing what and why. Keep changes focused.

## Adding a tracker to the conflict registry

Extend `Plugin_Detector::known_plugins()` (or use the
`pcm_known_tracking_plugins` filter in a PR test) with the plugin basename,
display name and the services it injects. If the plugin offers a supported
way to suppress its tag output, wire it in `integrations/` and declare it in
`Plugin_Conflict_Manager::mitigation_supported()` — with a source link to
the other plugin's documentation.

## Translations

Regenerate the template with `wp i18n make-pot . languages/consentguard.pot`
and submit locale files as standard `.po`.

## Security issues

Please report privately to the maintainers — do not open public issues for
vulnerabilities.
