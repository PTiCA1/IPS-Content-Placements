# Changelog

## 1.0.0-beta.2 — 2026-10-24

Build `1000002`, updateable from beta.1 without schema changes.

- Add the missing ACP navigation icon so the app is visible in the compact sidebar.
- Fix `template_store_missing` in the rule list, Pages warning and diagnostics by resolving shared ACP templates explicitly from `core/admin`.
- Document the separate Block placements menu and direct ACP link to the rule editor.
- Validate custom ACP tab labels/icons when building releases to prevent invisible menu entries.
- Exercise ACP controllers under the application's own dispatcher context in a regression test.
- Preserve existing rules, settings and generated hook registrations during the update.

## 1.0.0-beta.1 — 2026-10-24

Initial beta, build `1000001`, prepared for Invision Community 5.0.20 with Pages.

- CSS selector and native hook rules, before/after placement, block picker or one exact native block tag.
- First/nth/all occurrence handling and native IPS platform multiselect.
- Allowed/excluded groups including secondary memberships; application and URL filters.
- Original-request native block rendering, generated hook lifecycle and diagnostics.
- English UI, optional Czech application translation and standalone MIT source.
- Offline deterministic TAR, SHA256 checksums, metadata/syntax validation and automated tests.

Native advertisement integration is outside this release. ACP installation/upgrade and production behavior remain pending the owner's test installation. The beta has not been published to GitHub.
