# Upgrade & Migration Strategy

## How upgrades work

- `PCM_DB_VERSION` is compared to the stored `pcm_db_version` option.
  Schema changes ship as `dbDelta()`-compatible definitions in
  `Activator::create_tables()`, which is safe to re-run (dbDelta only adds
  what is missing). Re-activation or a future `admin_init` version check
  applies them.
- Settings are stored as one option and merged over `Settings::defaults()`
  at read time — new settings keys get sane defaults automatically, so no
  data migration is needed when options are added.
- Consent cookie format carries a `version` field. If a future release
  changes the cookie structure, the reader in `consent-manager.js` /
  `Consent_Manager::get_request_consent()` treats unreadable data as "no
  consent" — the visitor is simply re-prompted. Consent is never silently
  upgraded or assumed.

## Upgrading from another consent plugin

1. Keep the old plugin active while you configure this one (banner off).
2. Move tracker IDs into **ConsentGuard → Analytics** and custom
   snippets into the Script Manager.
3. Enable the banner here, deactivate the old plugin.
4. Visitors' old consent cookies are unknown to this plugin, so everyone is
   re-prompted once — this is intentional (their previous consent did not
   cover your new configuration).
5. Run the scanner to confirm nothing double-fires.

## Version policy

- Consent-relevant behaviour changes (new tracker, new category) should be
  accompanied by bumping **Settings → Consent version** so visitors are
  re-prompted.
- Semantic versioning for the plugin itself; breaking developer-API changes
  only in major versions, with deprecation notices one minor ahead where
  feasible.

## Rollback

Deactivating or deleting the plugin never deletes data unless "Delete plugin
data on uninstall" is enabled — you can roll back to a previous plugin
version and consent records/settings remain intact.
