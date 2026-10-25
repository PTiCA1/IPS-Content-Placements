# Development and release

The repository is the standalone application, not an IPS installation. Application runtime files are under `application/`; `tools/`, `tests/` and documentation remain outside the installable TAR. Do not add IPS source/developer packages, a forum database, uploads, `conf_global.php`, credentials or private placement exports to Git or CI artifacts.

The product is **Content Placements**, with the subtitle **for Invision Community 5**. The canonical project is [PTiCA1/IPS-Content-Placements](https://github.com/PTiCA1/IPS-Content-Placements), with [Issues](https://github.com/PTiCA1/IPS-Content-Placements/issues) for ordinary bug reports. The npm package slug remains `ips-content-placements`; the GitHub display name and canonical URL use `IPS-Content-Placements`. An existing local checkout may keep its historical `ips-pages-block-placements` directory name. None of these names changes the installed application identity.

```sh
git clone https://github.com/PTiCA1/IPS-Content-Placements.git
cd IPS-Content-Placements
```

## Build contract

- `data/application.json` fixes the installed key `rqblockplacements`; it must not change after release.
- Release TARs retain the `rqblockplacements-` prefix. Beta.9 updates the standard product and ACP main-tab names to Content Placements in every language, while submenus remain localized and administrator-customized text is preserved. Upgrade the existing application; do not create a second app key or rename stored rule identities.
- `data/versions.json` maps strictly increasing integer build IDs to human versions. The current beta is `1000012 → 1.0.0-beta.12`; earlier IDs remain in the map. Use new IDs for every update; never overwrite an existing published build.
- Keep the package version, asset version, application constants, language build and release filename consistent. Run the builder after a language change.
- `data/schema.json` is the clean-install schema. Later persisted-data changes need corresponding versioned `setup/upg_<build>/` migrations, not just an edited install schema.
- Beta.3 uses canonical `mobile`, `tablet`, `desktop` platform values. `Policy::platforms()` normalizes legacy OS selections on read and save, so this change needs no schema migration. An empty array permits all three classes.
- Author only the app's own original PHP, JS, JSON, XML and docs. The English, Czech, Slovak, Polish and German source arrays return string-to-string maps with identical key sets. Preserve placeholders and code examples when translating. The builder generates `data/lang.xml`, identity bootstrap `data/installLang.json` and `resources/lang-{cs,sk,pl,de}.xml` with the native application/version envelope.
- Beta.8 extends automatic translation installation from Czech to Slovak, Polish and German. Match existing locales by `cs`, `sk`, `pl`, `de` codes, including region variants, or Windows names beginning with Czech, Slovak, Polish or German. Read the matching bundled XML during installation and ACP upgrade. Fill missing translations only; preserve every existing custom value, including intentional empty text. Keep English defaults for other locales. Do not create a language, change language metadata or switch the site's default language. Existing translations can be refreshed explicitly, and languages added later can use the optional XML import.
- Beta.8 through beta.11 have no new schema changes; existing versioned migrations still apply when upgrading from older betas. Beta.11 adds repository metadata without feature changes.
- Beta.12 adds `inside-start` and `inside-end` alongside `before` and `after` in both placement modes. The clean-install schema and retryable `upg_1000012` migration widen `rqbp_rules.rule_position` from `VARCHAR(8)` to `VARCHAR(16)`. Keep existing values, rule IDs, sources, filters and ordering unchanged; retrying an already widened or partially completed upgrade must not lose data.
- Keep positive Pages-tag examples universal: `{block="my_new_block"}` requires an existing block with that key, or the reader must replace it with the actual key. Use the README Hello world expression for template examples; describe site-specific integrations generically.
- The native installer preserves `word_custom`, including bootstrap application titles. Beta.9 renames only the ten exact previous branding labels listed in `Translation::LEGACY_BRANDING` with a known beta.1–8 custom version; different or empty custom text survives. English and the four bundled locales receive their own updated labels. Other locales receive only the common application identity rename. A byte-exact text/version comparison in the SQL update protects concurrent edits; refresh language caches only after an affected row. An administrator's value identical to the old standard label cannot be distinguished from our original text.
- Runtime native-hook templates are derived from the site's rules. They must never be shipped from a developer's database. `data/customtemplates.json` contains only the static prepare/payload templates; app export keeps an explicit static allowlist. After native template import, `Application::installCustomTemplates()` recreates runtime markers from the surviving app table and invalidates their caches.

`python3 tools/build.py --check-reproducible` validates metadata and PHP syntax, generates language assets, then writes an uncompressed USTAR archive. Only files declared in `tools/package-files.json` below `application/`, plus this project's license, can enter the archive. Unknown or missing files stop the build; update the manifest deliberately after reviewing new runtime files. The archive root is app-relative (`Application.php`, `data/application.json`, ...), not `application/` or `rqblockplacements/`. All regular files use mode 0644, uid/gid 0 and the supplied `SOURCE_DATE_EPOCH` (default 0). No symlinks are accepted. The script verifies a second serialization is identical and writes SHA256 checksums.

The offline builder does not invoke or distribute the IPS application builder. The native ACP export path is available in an installed developer environment and uses live database/language state; a separate official developer package is needed to use that workflow. Neither route removes the need for an actual install/upgrade test.

## Test layers

1. `php tests/policy.php`: exact block input parsing, wildcards/query strings, allow/exclude precedence, primary/secondary groups, three device classes, legacy OS normalization and occurrences. No IPS source is loaded.
2. `php tests/runtime.php`: original minimal mocks exercise the real runtime with current-request block rendering, permission/cache guards and failure isolation. `php tests/acp-context.php` checks core ACP template context, the three translated device choices and the application → template → hook selection flow. `php tests/translations.php` exercises the actual bundled XML, locale detection, native batch counts, custom-text preservation and language cache refresh. Mocks are not a compatible replacement for IPS.
3. `python3 -m unittest discover -s tests -p 'test_*.py'`: deterministic app-root archive, authoring exclusions and prevention of symlink escapes.
4. `node tests/browser.mjs`: DOM placement, order/occurrences and browser behavior using original fixtures. Browser assets are real; the fixture is not a live forum.
5. `tools/validate.py`: data envelopes, languages, PHP syntax and package boundaries. Optional `--ips-source /local/private/ips` checks selected public method signatures read-only without copying files.
6. [Owner's ACP acceptance test](BETA-TEST.md), including a genuinely different next version to verify the update lifecycle.

CI runs the public layers only. Keep any licensed IPS integration environment private, and upload only test reports generated without proprietary source or forum content. Public CI never downloads IPS or calls a user's forum.

`php tests/template-content.php` checks the template execution boundary. Optionally set `RQBP_IPS_THEME_SOURCE=/local/private/ips_v5.0.20` for this test to compile the sample with the actual local IPS compiler and an original integration stub; no platform files are copied. `php tests/migration.php` covers additive schema changes and interrupted-upgrade retries. These fixtures do not test live site-specific integrations.

## Runtime review points

Beta.5 adds `content_type` and `template_code` via the retryable `upg_1000005` migration; old rows default to `block`. Template code is trusted administrator code behind `rqbp_manage_code`, not a sandbox. Syntax validation compiles native IPS syntax and parses the resulting PHP without calling it. Front-end rendering uses the native template runner only after eligibility checks, with output-buffer cleanup on failure. Cache compiled code only; never share rendered HTML. Template routes bypass shared page caching even when audience filters omit their output. Site-specific integration extensions are not included in this repository or package.

Native `Block::display()` runs in the original request so Pages owns its permissions, parsing and asset behavior. The application must not share rendered block HTML between users. Both omitted and displayed platform variants need compatible guest cache behavior. Group exclusions win even when a different group allows access. URL exclusions win over includes. Hook template generation uses fixed code and integer rule IDs, never arbitrary user template code. Native hook output and CSS insertion must be re-entry-safe and must not target their own placement wrappers.

The beta.12 hook editor follows application → template → hook → insertion type, using the live catalog of enabled front-end templates. The insertion type uses the installed IPS core language keys `template_custom_before`, `template_custom_inside_start`, `template_custom_inside_end` and `template_custom_after`; do not copy platform language strings into the add-on. New application help keys provide the updated instructions without overwriting administrator text. Both native-hook and CSS placement must validate the selected target/position combination: `body` allows only inside-start/end, and block content is not supported in `head` or `title`. The native IPS editor may list these hooks for other purposes; this application's catalog must not offer them for block content. Source/API verification uses IPS 5.0.20; the intended target remains 5.0.20+, with later versions and native beta.12 behavior requiring acceptance testing.

Device classification uses the native IPS platform plus raw user agent: Android with `Mobile` is Mobile; Android without it and iPad/Kindle are Tablet; phones are Mobile; remaining/unknown devices are Desktop. Viewport resizing is irrelevant. An iPad presenting a desktop user agent cannot be reliably distinguished on the server. Legacy Android selections normalize to both Mobile and Tablet; PC and old unknown selections normalize to Desktop.

Before release, test assets from real Pages widget/feed blocks, deferred script initialization, missing/denied blocks, multiple placements, and the actual CDN. Existing third-party block scripts may not support repeated instances.

## Release process

1. Review changes and increase versions/migrations as needed. Update changelog and translations.
2. Run `npm ci`, `npm test`, `npm run build`, and validate the resulting TAR. Check the archive listing contains only this application and the original MIT license.
3. Check the generated files are committed and inspect `git diff`. A repeat build from the same checkout/epoch must have the same SHA256.
4. Give the beta TAR, four optional `-cs.xml`, `-sk.xml`, `-pl.xml`, `-de.xml` translation files, SHA256SUMS and acceptance checklist to the owner. SHA256SUMS covers all five release artifacts. Sharing source code is separate from stable-release acceptance; native testing must finish before calling a release stable.
5. After acceptance, tag the tested commit (for example `v1.0.0-beta.12`) and attach exactly those tested artifacts to a [GitHub release](https://github.com/PTiCA1/IPS-Content-Placements/releases). Source publication does not create a release or upload its artifacts. The workflow deliberately does not publish releases automatically.
6. Call a release stable only after the native requirements and real version-to-version upgrade have passed. Record the exact IPS/PHP/theme versions tested rather than assuming compatibility with every IPS 5 version.

MIT covers this add-on's original code only. The [Invision Community software EULA](https://invisioncommunity.com/legal/license/) applies separately to the platform; no IPS code or license keys belong in this repository or its downloadable artifacts.

## Security release checks

`php tests/page-cache.php` covers response-header overrides. Optionally set `RQBP_IPS_OUTPUT_SOURCE=/local/private/ips_v5.0.20` to run the actual native Output method with isolated dependencies. `RuleAccess` owns every code-activation permission and `PageCache` owns all private response headers. Runtime cache guards run before audience filtering for all active matching routes, including blocks with native permissions. Preserve these boundaries and the package manifest during refactoring. See SECURITY.md and the dated audit report for reviewed findings and deployment limits.
