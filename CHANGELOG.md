# Changelog

## 1.0.0-beta.5 — 2026-10-25

Build `1000005`; additive, retryable migration from previous betas.

- Add custom IPS template code as a content source, including native `{expression="…" raw="true"}` calls to installed integrations.
- Restrict code management with the separate `rqbp_manage_code` ACP permission. Validate compiled syntax without invoking the expression during save or ACP diagnostics.
- Apply the same device/group/URL and placement filters before execution. Template rules run without Pages and bypass shared page caching; block rules retain Pages permissions.
- Preserve existing block rules through an additive schema migration. Add tests for native rendering boundaries, permissions, failures and partial-upgrade retries.
- Native installation and the owner's actual MafraApi integration remain pending live acceptance.

## 1.0.0-beta.4 — 2026-10-24

Build `1000004`, updateable from earlier betas without schema changes.

- Apply the bundled Czech application translation automatically during installation and upgrade for existing Czech locales, including `cs_CZ`, `cs-CZ` and `Czech_…`; no manual XML import is required.
- Preserve existing custom text, including intentional empty values. New translation keys fill automatically; an explicit XML refresh remains available for existing text or Czech languages added later.
- Keep English defaults for other languages, without changing language metadata or the site's default language.
- Document automatic Czech and preservation checks in both acceptance checklists. Device targeting and beta.4 installation/upgrade still require native acceptance testing; beta.3 has not been confirmed on the owner's forum.

## 1.0.0-beta.3 — 2026-10-24

Build `1000003`, updateable from beta.2 without schema changes.

- Replace the OS platform list with exactly Mobile, Tablet and Desktop; an empty selection allows all three.
- Classify devices from the native IPS platform and user-agent string. Android phones and tablets are distinguished by the `Mobile` token; viewport resizing does not affect targeting.
- Normalize legacy OS selections when reading existing rules and store canonical device classes on save. Old Android targeting covers Mobile and Tablet.
- Keep desktop-mode iPads in Desktop when their user agent cannot be distinguished from a desktop device.

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
