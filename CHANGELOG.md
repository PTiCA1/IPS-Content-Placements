# Changelog

## 1.0.0-beta.15 — 2026-10-25

Build `1000015`; native pagination parser compatibility fix, without schema or rule changes.

- Accept `input` and `button` controls as direct children of `ul` when parsing native IPS pagination. Beta.14 could reject the entire controller fragment as unsupported HTML even when the selector matched correctly in the browser, preventing server insertion.
- Keep insertion-context, rendered-content, nested-form, permission and cache guards unchanged. Preserve all `css`, `server` and `hook` rules without rewriting or automatic mode conversion.
- Add original regression coverage for pagination parsing, successful server placement and preservation of rejected unsafe insertions; do not bundle private forum HTML or licensed platform source.
- No database migration is required. Retain the 5.0.20+ target, the 5.0.20 source-verification baseline and existing older migrations. Native beta.14 → beta.15 installation, pagination placement and upgrade acceptance remain pending. Earlier release notes and the dated audit remain historical.

## 1.0.0-beta.14 — 2026-10-25

Build `1000014`; opt-in server CSS placement, without new database schema changes.

- Add **CSS selector — server** alongside the existing JavaScript selector mode and native hooks. Preserve stored `css`/`hook` rules; `server` fits the existing mode column and is never selected by automatic conversion or fallback.
- Process supported normal full HTML controller output through `Loader::onFinish`, scoped to virtual `div#ipsLayout__main`. Exclude global header/navigation/footer/head and AJAX; standalone Pages/direct-output routes may bypass this lifecycle.
- Match a strict CSS subset with one deduplicated document-order comma union and global first/nth/all handling. Reject unsupported syntax, use only HTML context without computed styles, preserve original bytes and skip unsupported/unbalanced input rather than repairing it.
- Render content only after eligibility, native block permissions, a matching occurrence and safe context. Put server placements directly into response HTML without JavaScript insertion/payload; browser parsing handles their scripts, with ordinary script-order limitations.
- Add localized mode/help/error/diagnostic keys in all five languages, universal selector examples and server-specific acceptance checks. No new schema migration; retain older migrations, application identity, permissions and cache boundaries.
- Retain the 5.0.20+ target and 5.0.20 source-verification baseline. Native beta.13 → beta.14 lifecycle, selector/script behavior, installation and upgrade acceptance remain pending. Earlier release notes and the dated security audit remain historical.

## 1.0.0-beta.13 — 2026-10-25

Build `1000013`; native metadata-only head placement, without new database schema changes.

- Enable the native `head` hook for inside-start/end. Keep native head/body outside positions invalid, CSS head targets unsupported, and `title` or unsupported hooks inside head unavailable.
- Render head content without a `div` wrapper. Validate the actual public output from either Pages blocks or custom template code and accept only `script`, `style`, `meta` and `link` elements; omit invalid visible HTML/text or other unsupported output as a whole placement.
- Keep ACP save and diagnostics nonexecuting: they cannot infer the final rendered fragment. Add fresh head/position help and a head-content error in all five application languages.
- Preserve the beta.12 position-column migration and existing rules. Retain the 5.0.20+ target and 5.0.20 source-verification baseline; native beta.12 → beta.13 head placement, installation and upgrade acceptance remain pending. Earlier release notes and the dated security audit remain historical.

## 1.0.0-beta.12 — 2026-10-25

Build `1000012`; four insertion positions and a retryable position-column migration.

- Add `inside-start` and `inside-end` alongside `before` and `after` for CSS and native-hook rules. Keep existing before/after rules unchanged.
- Select native hooks through application → template → hook → insertion type, using the current catalog and installed IPS core position labels. Add localized help in all five application languages.
- Allow `body` only for inside positions; exclude `head`, `title` and other unsupported block-content contexts from the hook picker.
- Widen `rqbp_rules.rule_position` from `VARCHAR(8)` to `VARCHAR(16)` in the clean-install schema and `upg_1000012`, preserving existing rules and supporting retries after a partial upgrade.
- Retain the 5.0.20+ compatibility target with source verification against 5.0.20. Native beta.11 → beta.12 installation, placement and upgrade acceptance remain pending. The dated security audit remains historical.

## 1.0.0-beta.11 — 2026-10-25

Build `1000011`; repository metadata update, without feature or schema changes.

- Set the canonical project to [PTiCA1/IPS-Content-Placements](https://github.com/PTiCA1/IPS-Content-Placements) and link ordinary bug reports to its [Issues](https://github.com/PTiCA1/IPS-Content-Placements/issues).
- Keep the npm slug `ips-content-placements`, installed key `rqblockplacements` and release filename prefix unchanged.
- Document source checkout and reporting routes. Source publication is separate from release-artifact uploads and native testing required before a stable release.
- Native beta.10 → beta.11 installation and upgrade acceptance remain pending.

## 1.0.0-beta.10 — 2026-10-25

Build `1000010`; universal examples and documentation update, without schema changes.

- Standardize positive Pages-tag examples on `{block="my_new_block"}` and explain that the block must exist or the key must be replaced with the actual one.
- Keep the standalone Hello world template example and use generic descriptions for site-specific integrations.
- Add a new ACP help key so existing installations receive the revised block example without overwriting custom translations.
- Retain the Content Placements name, installed application identity and existing rules. Native beta.9 → beta.10 upgrade acceptance remains pending.

## 1.0.0-beta.9 — 2026-10-25

Build `1000009`; rebranding release, without new schema changes.

- Rename the product to **Content Placements**, with the subtitle **for Invision Community 5**. Use Content Placements for the standard ACP main-tab name in every language while keeping submenus localized.
- Refresh the application's standard names during upgrades and preserve administrator-customized text, existing rules, settings and permissions.
- Use `ips-content-placements` for the npm package and planned public repository. Keep the installed app key `rqblockplacements`, release TAR prefix and historical local checkout directory unchanged so updates target the existing application.
- Native rebranding and upgrade acceptance remain pending. Earlier changelog entries and the dated security audit retain their historical names.

## 1.0.0-beta.8 — 2026-10-25

Build `1000008`; translation release, without new schema changes.

- Add complete Slovak, Polish and German translations alongside Czech, with all 148 application keys in each language.
- Fill missing translations automatically during installation and ACP upgrades for existing `cs`, `sk`, `pl`, `de` locales, including regional and Windows Czech/Slovak/Polish/German forms.
- Preserve custom text, including intentional empty values. Do not create languages, change their metadata or switch the site's default language; other locales retain English defaults.
- Package the TAR, four optional language XML files and SHA256SUMS. XML imports remain available for explicit refreshes or languages added later.
- Live multilingual installation and upgrade acceptance remain pending. The dated security audit is unchanged and does not certify this translation release.

## 1.0.0-beta.7 — 2026-10-25

Build `1000007`; documentation and ACP help update, without schema changes.

- Replace the integration-specific README and template-help sample with the universal `{expression="'Hello world'"}` example in English and Czech.
- Use a new help language key so existing installations receive the updated example without overwriting their custom translations.

## 1.0.0-beta.6 — 2026-10-25

Build `1000006`; security and refactoring release, without new schema changes.

- Prevent public page-cache variants across guest groups and native block permissions. Override earlier public IPS response headers with private/no-store for every route with an eligible active rule, before audience checks.
- Centralize code permissions in `RuleAccess`, including global reactivation of enabled template rules. Block administrators retain the panic stop.
- Cancel stale placement processing after navigation, stop subsequent old scripts, correctly skip unsupported module/nomodule scripts and coalesce repeated content events.
- Add a reviewed package-file manifest, header-failure regressions and adversarial browser/permission/cache checks.
- See `docs/SECURITY-AUDIT-2026-10-25.md` for findings, evidence and limits. Purge pre-existing CDN/reverse-proxy cache after upgrading.

## 1.0.0-beta.5 — 2026-10-25

Build `1000005`; additive, retryable migration from previous betas.

- Add custom IPS template code as a content source, including native `{expression="…" raw="true"}` calls to installed integrations.
- Restrict code management with the separate `rqbp_manage_code` ACP permission. Validate compiled syntax without invoking the expression during save or ACP diagnostics.
- Apply the same device/group/URL and placement filters before execution. Template rules run without Pages and bypass shared page caching; block rules retain Pages permissions.
- Preserve existing block rules through an additive schema migration. Add tests for native rendering boundaries, permissions, failures and partial-upgrade retries.
- Native installation and the owner's live custom integration remain pending live acceptance.

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

Native advertisement integration is outside this release. ACP installation/upgrade and production behavior remain pending the owner's test installation. The initial beta.1 was not published to GitHub.
