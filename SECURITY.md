# Security Policy

## Supported versions

The latest release on the `master` branch of
[senthilnasa/ConsentGuard](https://github.com/senthilnasa/ConsentGuard)
receives security fixes.

## Reporting a vulnerability

Please **do not open a public issue** for security problems. Instead, use
GitHub's private vulnerability reporting on the repository
(*Security → Report a vulnerability*). You will receive a response as soon
as possible; please allow reasonable time for a fix before public
disclosure.

## Design notes for reviewers

The plugin's threat model and controls are documented in
[consentguard/docs/SECURITY.md](consentguard/docs/SECURITY.md):
nonce/capability checks on every admin action and REST route, capability-gated
script storage (`unfiltered_html`), strict input validation, prepared
statements, a rate-limited public consent endpoint that accepts no PII, and
no external requests at runtime.
