# Development and release

The repository is the standalone application, not an IPS installation. Application runtime files are under `application/`; `tools/`, `tests/` and documentation remain outside the installable TAR. Do not add IPS source/developer packages, a forum database, uploads, `conf_global.php`, credentials or private placement exports to Git or CI artifacts.

## Build contract

- `data/application.json` fixes the installed key `rqblockplacements`; it must not change after release.
- `data/versions.json` maps strictly increasing integer build IDs to human versions. The current beta is `1000003 → 1.0.0-beta.3`; earlier IDs remain in the map. Use new IDs for every update; never overwrite an existing published build.
- Keep the package version, asset version, application constants, language build and release filename consistent. Run the builder after a language change.
- `data/schema.json` is the clean-install schema. Later persisted-data changes need corresponding versioned `setup/upg_<build>/` migrations, not just an edited install schema.
- Beta.3 uses canonical `mobile`, `tablet`, `desktop` platform values. `Policy::platforms()` normalizes legacy OS selections on read and save, so this change needs no schema migration. An empty array permits all three classes.
- Author only the app's own original PHP, JS, JSON, XML and docs. English/Czech source arrays return string-to-string maps. The builder generates `data/lang.xml`, identity bootstrap `data/installLang.json` and `resources/lang-cs.xml` with the native application/version envelope.
- Runtime native-hook templates are derived from the site's rules. They must never be shipped from a developer's database. `data/customtemplates.json` contains only the static prepare/payload templates; app export keeps an explicit static allowlist. After native template import, `Application::installCustomTemplates()` recreates runtime markers from the surviving app table and invalidates their caches.

`python3 tools/build.py --check-reproducible` validates metadata and PHP syntax, generates language assets, then writes an uncompressed USTAR archive. Only allowlisted content below `application/`, plus this project's license, can enter the archive. The archive root is app-relative (`Application.php`, `data/application.json`, ...), not `application/` or `rqblockplacements/`. All regular files use mode 0644, uid/gid 0 and the supplied `SOURCE_DATE_EPOCH` (default 0). No symlinks are accepted. The script verifies a second serialization is identical and writes SHA256 checksums.

The offline builder does not invoke or distribute the IPS application builder. The native ACP export path is available in an installed developer environment and uses live database/language state; a separate official developer package is needed to use that workflow. Neither route removes the need for an actual install/upgrade test.

## Test layers

1. `php tests/policy.php`: exact block input parsing, wildcards/query strings, allow/exclude precedence, primary/secondary groups, three device classes, legacy OS normalization and occurrences. No IPS source is loaded.
2. `php tests/runtime.php`: original minimal mocks exercise the real runtime with current-request block rendering, permission/cache guards and failure isolation. `php tests/acp-context.php` checks core ACP template context and the three translated device choices. Mocks are not a compatible replacement for IPS.
3. `python3 -m unittest discover -s tests -p 'test_*.py'`: deterministic app-root archive, authoring exclusions and prevention of symlink escapes.
4. `node tests/browser.mjs`: DOM placement, order/occurrences and browser behavior using original fixtures. Browser assets are real; the fixture is not a live forum.
5. `tools/validate.py`: data envelopes, languages, PHP syntax and package boundaries. Optional `--ips-source /local/private/ips` checks selected public method signatures read-only without copying files.
6. [Owner's ACP acceptance test](BETA-TEST.md), including a genuinely different next version to verify the update lifecycle.

CI runs the public layers only. Keep any licensed IPS integration environment private, and upload only test reports generated without proprietary source or forum content. Public CI never downloads IPS or calls a user's forum.

## Runtime review points

Native `Block::display()` runs in the original request so Pages owns its permissions, parsing and asset behavior. The application must not share rendered block HTML between users. Both omitted and displayed platform variants need compatible guest cache behavior. Group exclusions win even when a different group allows access. URL exclusions win over includes. Hook template generation uses fixed code and integer rule IDs, never arbitrary user template code. Native hook output and CSS insertion must be re-entry-safe and must not target their own placement wrappers.

Device classification uses the native IPS platform plus raw user agent: Android with `Mobile` is Mobile; Android without it and iPad/Kindle are Tablet; phones are Mobile; remaining/unknown devices are Desktop. Viewport resizing is irrelevant. An iPad presenting a desktop user agent cannot be reliably distinguished on the server. Legacy Android selections normalize to both Mobile and Tablet; PC and old unknown selections normalize to Desktop.

Before release, test assets from real Pages widget/feed blocks, deferred script initialization, missing/denied blocks, multiple placements, and the actual CDN. Existing third-party block scripts may not support repeated instances.

## Release process

1. Review changes and increase versions/migrations as needed. Update changelog and translations.
2. Run `npm ci`, `npm test`, `npm run build`, and validate the resulting TAR. Check the archive listing contains only this application and the original MIT license.
3. Check the generated files are committed and inspect `git diff`. A repeat build from the same checkout/epoch must have the same SHA256.
4. Give the beta TAR, Czech XML, SHA256SUMS and acceptance checklist to the owner. Do not publish while the native acceptance test is pending.
5. After acceptance, tag the tested commit (for example `v1.0.0-beta.3`) and attach exactly those tested artifacts to a GitHub release. The workflow deliberately does not publish releases automatically.
6. Call a release stable only after the native requirements and real version-to-version upgrade have passed. Record the exact IPS/PHP/theme versions tested rather than assuming compatibility with every IPS 5 version.

MIT covers this add-on's original code only. The [Invision Community software EULA](https://invisioncommunity.com/legal/license/) applies separately to the platform; no IPS code or license keys belong in this repository or its downloadable artifacts.
