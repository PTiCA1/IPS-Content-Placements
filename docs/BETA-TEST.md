# ACP beta acceptance test

Status: the owner confirmed that the **beta.2 rule editor works** after the beta.1 ACP fixes. **Device targeting, translations and native upgrades remain pending live verification.** Beta.13 adds the native head hook with metadata-only output validation; native verification of this behavior is pending. Source publication is separate from this acceptance checklist. Automated mocks and browser fixtures do not establish native installation or upgrade compatibility. Record results before calling the package stable.

Use a licensed, private **IPS 5.0.20+** test installation with **Pages** and a database/file backup. The source-verification baseline is **5.0.20**; record the exact version when testing a later release. Record the PHP version, IPS build, theme, browser, CDN/cache configuration and application TAR SHA256. The current package is **`1.0.0-beta.13`, build `1000013`**; test beta.12 → beta.13 and an earlier-beta upgrade that exercises existing migrations. Beta.13 has no new database changes; retain the beta.12 position-width migration for older upgrades.

## Installation and basic placement

1. Upload `rqblockplacements-1.0.0-beta.13.tar` through ACP Applications. Confirm no installer error, version/build `1.0.0-beta.13` / `1000013`, and a visible grid icon near the bottom of the normal compact ACP sidebar. Open **Content Placements → Placement rules**, then **Content Placements → Settings**. The main tab uses the brand in every language; submenus remain localized, such as **Pravidla umístění / Nastavení** in Czech. Confirm the subtitle **for Invision Community 5**. This is a separate application tab, not a submenu of native Advertisements or Pages.
2. Create a public Pages HTML block with the key `my_new_block`, a unique `RQBP TEST` heading and a link. If using an existing block, replace `my_new_block` in the example with its actual key. Confirm its original native display works first.
3. Create a CSS rule for a category actually on your forum, for example `li[data-categoryID="4"]`, **after**, first match, unrestricted audience. Check it appears between categories and does not appear between topic posts. Inspect the real category ID before choosing the selector.
4. Test **before**, **inside-start**, **inside-end** and **after** with a suitable container. Before/after must create siblings; inside-start/end must create the first/last child. Check target/category ordering and absence of duplicated block output.
5. Create a separate native-hook rule through **application → template → hook → insertion type**. Confirm changing the application updates the template choices and changing the template updates the hook choices. Use safe available hooks in `core/front/global/globalTemplate`: test `main` with suitable positions and `body` with only inside-start/end. A hook missing from your installed version is not a selectable target.
6. Select the block through the picker, then switch to the manual source and use one exact `{block="my_new_block"}`. A block with that key must exist; otherwise use your block's actual key. Both methods should select the same existing block. Extra HTML, two tags and other template expressions must be rejected.
7. Confirm the application project link targets [PTiCA1/IPS-Content-Placements](https://github.com/PTiCA1/IPS-Content-Placements) and ordinary bug reports use its [Issues](https://github.com/PTiCA1/IPS-Content-Placements/issues). Reporting a security issue must follow the private-reporting guidance in SECURITY.md.

Direct navigation fallback: retain the actual ACP directory in your current address and open its `index.php` with `?app=rqblockplacements&module=placements&controller=rules`. A renamed ACP folder must remain unchanged. On beta.1 a blank item near the bottom of the compact sidebar may still open the application; beta.2 must show its icon without depending on hover or a screen wider than 1,900 px.

## Four positions and HTML contexts

- For both CSS and ordinary native hooks, exercise all four positions with a simple block and the Hello world template source. Use a valid body-content target for this visible output. Check first/nth/all occurrences and ordering when two rules use the same position.
- Target `body` with CSS and, when available, the native `body` hook. Inside-start/end must stay within the body. Before/after must not insert content outside it. A forged unsafe native-hook position must be rejected on save; CSS placement must skip an unsafe target/position combination.
- Confirm `title` and unsupported hooks within head remain absent from the application picker even if IPS lists them in its own custom-template editor. Unsupported CSS target/position combinations, including head targets, must not insert content.
- Reopen and save a hook rule. Its application, template, hook and position must remain selected; changing an upstream selection must not silently retain a hook from another template. Position names must use the installed IPS core translations, not unresolved language keys.
- Verify inside-start/end values survive save, reload and a later edit without truncation. Keep previously stored before/after rules in place and confirm their placement is unchanged.

## Native metadata-only head hook

- Select application Core, template `core/front/global/globalTemplate`, hook `head`. Test inside-start and inside-end; direct submissions of before/after must be rejected, as for the body hook.
- Use the metadata example from README as custom template code and inspect the actual document source. The element must be inside head with no added `div` wrapper. Exercise output containing each allowed element type: `script`, `style`, `meta` and `link`.
- Repeat with a Pages block whose rendered output is metadata-only. A rich-text/feed block that adds a visible wrapper must be omitted; the source type alone does not prove its final HTML is valid.
- Test visible text, a `div`, `p`, `title` or mixed metadata/visible markup. The entire placement must be skipped, without partial output or relocation into the body. Check the administrator diagnostics and logs.
- Save and open ACP diagnostics for output-producing template code without invoking it. Final-fragment validation must happen only on the public request, after audience filters and native block permissions. A denied visitor must receive no output or code execution.
- Confirm CSS selectors targeting head or its descendants remain unsupported. Recheck ordinary body/CSS placements so head support does not change their wrappers or visibility.

## Automatic Czech, Slovak, Polish and German

- Before installation or upgrade, retain existing Czech, Slovak, Polish and German languages plus an English/unsupported locale. Exercise `cs`, `sk`, `pl`, `de` and regional forms such as `cs_CZ`, `sk-SK`, `pl_PL`, `de-DE`, as well as Windows Czech/Slovak/Polish/German locale names. Each supported locale must receive its own application translation without a separate XML import; other locales keep English defaults.
- Check every application key in each translation: menus, permission labels, rule fields, device labels, help, errors and diagnostics. Preserve working code examples, placeholders and the navigation icon. Switching between existing languages must select the appropriate text.
- Before upgrading, customize one application text and set another to an intentional empty value in each supported language. Both must survive. Missing translations must receive the matching text. No language may be created, and the site's default language, language names, locale codes and other metadata must remain unchanged.
- Verify the release provides one TAR, four optional `-cs.xml`, `-sk.xml`, `-pl.xml`, `-de.xml` files and SHA256SUMS covering all five artifacts. Optionally refresh an existing translation with the matching XML. Add a supported language after installing the application and verify its documented XML import path. This explicit native import is separate from the automatic app update.

## Preserving the existing product name

- Upgrade beta.12 to beta.13 through the existing application entry. Confirm the key `rqblockplacements`, rules, settings and administrator permissions remain unchanged; no second application should appear.
- Keep the standard product and main-tab labels **Content Placements**, the subtitle **for Invision Community 5** and localized submenus. Administrator-customized labels, including intentional empty text, must remain intact.

## Filtering and occurrences

- Confirm Platforms has exactly **Mobile, Tablet, Desktop** (Czech: **Mobil, Tablet, Desktop**), with no OS or Unknown options. Test an empty selection, each class separately and Mobile + Tablet together. Use an iPhone, an Android phone with `Mobile` in its user agent, an Android tablet without that token, an iPad/Kindle, a desktop and an unrecognized user agent (Desktop). Resizing must not change the class. An iPad presenting a desktop user agent may be detected as Desktop.
- Test a member whose primary group is allowed and secondary group is excluded: exclusion must win. Test an allowed secondary group, an unlisted member, an ordinary administrator and a guest.
- Restrict to Forums, then visit Pages and Forums. Test URL include `/topic/*`, then an overlapping exclude. Test a query string and a board installed in a subfolder.
- With at least three real targets, test first, nth=2 and all. Test a missing nth target. Verify the application neither targets its own inserted block nor duplicates output after IPS dynamic content events.
- Add two rules around the same target. Reorder them, edit them, duplicate one, disable it and delete it. Confirm predictable order and no orphan hook output.

## Rendering, assets and failure cases

- Render a feed/widget block that needs CSS and JS, as well as simple HTML. Test both CSS placement and a global native hook after the page header. Check browser console and network failures.
- Use a request-aware block in the test installation. It must see the original page path/query and the current user. Verify there is no second block-rendering HTTP endpoint request.
- Test a private, inactive and deleted block. Test a missing CSS target and a hook that is not invoked on that route. Visitors should receive no implementation error text or private block HTML.
- Render the same interactive block at multiple targets and inspect duplicate HTML IDs and initialization. Native block contents may require single-instance use.
- Open diagnostics as an authorized administrator. Confirm an ordinary member/guest cannot read administrator diagnostics or change rules, including by requesting action URLs directly.
- Load relevant guest URLs with desktop and mobile user agents through the actual CDN. One audience's output must never be served as another's cached page. Purge existing affected guest-page cache when rules change.
- Disable the global switch, then the app itself. Confirm placements stop. Disable Pages in the test installation: placements should fail closed without a visitor error.

## Upgrade and cleanup

1. Save representative CSS and native-hook rules, ordering and group filters in the installed beta. In beta.2, also retain legacy platform selections (Android, iPhone, iPad, Windows and Unknown); in beta.3, retain canonical device choices. Export a backup of the database.
2. Test **beta.12 (`1000012`) → beta.13 (`1000013`)** using `rqblockplacements-1.0.0-beta.13.tar` through ACP: this update has no new database changes and must preserve the existing 16-character position field and rules. Also test an older version such as beta.2 (`1000002`) → beta.13, which must apply beta.12's `VARCHAR(8)` → `VARCHAR(16)` widening without changing values or rules. Retry a partial upgrade and an already widened column; no rows may be lost or reset. Re-uploading the same beta does **not** establish upgrade compatibility. Beta.2 editor access is confirmed, while subsequent live acceptance remains pending.
3. Confirm rules/settings remain and native hook markers are restored once. Android must become Mobile + Tablet, iPhone Mobile, iPad/Kindle/PlayBook Tablet, and Windows/Unknown Desktop. Opening a rule normalizes old platform values; saving those canonical device values needs no additional schema change. Repeat an edit/reconcile and check no duplicate markers or placements. Normal menu navigation and the direct URL must open the same rule table.
4. Check all four automatic translations and preservation of custom text as described above, without importing XML. Confirm unsupported locales still use English defaults. A later explicitly requested XML refresh must affect only this application's translations; verify the intended language after native import.
5. On the disposable test installation, uninstall. Confirm this app's tables/settings/markers are removed and existing Pages blocks still exist.

Keep stable 1.0.0 pending until installation, assets, permissions, cache isolation and a real version-to-version upgrade all pass. Before sharing a defect report, remove cookies, CSRF keys, personal data and any private block contents.

| Check | Result | Notes / reproduction |
| --- | --- | --- |
| Clean ACP install | Pending | |
| Compact ACP menu icon / rule-editor navigation | Pending | |
| CSS category placement | Pending | |
| Native hook placement | Pending | |
| Four positions / body restrictions | Pending | |
| Native head / metadata validation / CSS head rejection | Pending | |
| Application → template → hook picker | Pending | |
| Permissions / secondary groups | Pending | |
| Platforms / URL filters | Pending | |
| First / nth / all / ordering | Pending | |
| Native assets / original request | Pending | |
| Guest CDN isolation | Pending | |
| Diagnostics / ACP restrictions | Pending | |
| Earlier beta → beta.13 upgrade / platform conversion | Pending | |
| Position column widening / retry / existing-rule preservation | Pending | |
| Project / issue-reporting metadata | Pending | |
| Application identity / product name / custom labels | Pending | |
| Automatic CS / SK / PL / DE translations | Pending | |
| Custom text / intentional empty values preserved | Pending | |
| English defaults / unchanged language settings | Pending | |
| Disable / uninstall | Pending | |

## Template code (introduced in beta.5)

- Upgrade beta.4 → beta.13 and retain existing block rules through the additive template-code migration. Retrying a partial migration must preserve data.
- Choose custom IPS template code and use the Hello world example from README. An eligible front-end request must display Hello world without an additional extension. Save and ACP diagnostics must only validate syntax.
- Exercise both placement modes, device/group/URL filters, excluded guests, all four positions, repetition and absent selectors. Code is prepared server-side before CSS target discovery.
- A restricted administrator without rqbp_manage_code must not read code or submit a forged source, enable, duplicate or edit code rules. Block management remains available.
- Custom code must work without Pages; Pages block rules must be omitted. Syntax and API failures must not leak output.

## Beta.6 security acceptance

- Purge affected CDN/reverse-proxy cache after upgrading. Check final private/no-store headers on archived topics and search, for both eligible and omitted audiences.
- Exercise different guest/bot groups and native block permissions without an explicit device filter.
- A block administrator without code permission must not globally reactivate enabled code rules; the panic stop remains available.
- Navigate while an ad script is pending: remove stale wrappers immediately and stop subsequent old scripts. An already attached external script may still execute.
