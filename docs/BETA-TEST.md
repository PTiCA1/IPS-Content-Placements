# ACP beta acceptance test

Status: the owner confirmed that the **beta.2 rule editor works** after the beta.1 ACP fixes. **Device targeting introduced in beta.3, automatic Czech added in beta.4 and native upgrade acceptance remain pending.** Automated mocks and browser fixtures do not establish native installation or upgrade compatibility. Record results before publishing or calling the package stable.

Use a licensed, private IPS **5.0.20 + Pages** test installation with a database/file backup. Record the PHP version, IPS build, theme, browser, CDN/cache configuration and application TAR SHA256. The current package is **`1.0.0-beta.7`, build `1000007`**; test upgrading from beta.2 (`1000002`) and beta.3 (`1000003`).

## Installation and basic placement

1. Upload `rqblockplacements-1.0.0-beta.7.tar` through ACP Applications. Confirm no installer error, version/build `1.0.0-beta.7` / `1000007`, and a visible grid icon near the bottom of the normal compact ACP sidebar. Open **Block placements → Placement rules**, then **Block placements → Settings**. In an existing Czech language these are automatically **Umístění bloků → Pravidla umístění / Nastavení**, without importing XML. This is a separate application tab, not a submenu of native Advertisements or Pages.
2. Create a public Pages HTML block with a unique `RQBP TEST` heading and a link. Confirm its original native display works first.
3. Create a CSS rule for a category actually on your forum, for example `li[data-categoryID="4"]`, **after**, first match, unrestricted audience. Check it appears between categories and does not appear between topic posts. Inspect the real category ID before choosing the selector.
4. Change to **before**. Check the target/category ordering and absence of duplicated block output.
5. Create a separate native-hook rule using an available safe hook, such as the `main` hook in `core/front/global/globalTemplate`, **before** or **after**. Check it displays once with valid surrounding markup. A native hook missing from your installed version is not a selectable target.
6. Select a Pages block through the picker, then switch to the manual source and use one exact `{block="the_actual_key"}`. Both should select the same existing block. Extra HTML, two tags and other template expressions must be rejected.

Direct navigation fallback: retain the actual ACP directory in your current address and open its `index.php` with `?app=rqblockplacements&module=placements&controller=rules`. A renamed ACP folder must remain unchanged. On beta.1 a blank item near the bottom of the compact sidebar may still open the application; beta.2 must show its icon without depending on hover or a screen wider than 1,900 px.

## Automatic Czech

- Before installation or upgrade, retain an existing Czech language and an English/non-Czech language. Test Czech locale variants `cs_CZ`, `cs-CZ` and `Czech_…` where available. The app's Czech menu, rule fields, device labels and messages must appear without a separate XML import; non-Czech languages keep English defaults.
- Before upgrading, customize one application text and set another to an intentional empty value. Both must survive. New translation keys must receive Czech text. The site's default language and each language's name, locale and other metadata must remain unchanged.
- Optionally refresh existing translated values using the separate Czech XML. Add a Czech language after installing the application and verify the documented XML import path. This explicit native import is separate from the automatic app update.

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
2. Specifically test **beta.2 (`1000002`) → beta.7 (`1000007`)** and **beta.3 (`1000003`) → beta.7 (`1000007`)** using `rqblockplacements-1.0.0-beta.7.tar` through ACP. Re-uploading the same beta does **not** establish migration compatibility. For the earlier beta.1 → beta.2 upgrade, the missing icon and `template_store_missing` were the reported defects; beta.2 editor access is confirmed, while beta.3 acceptance remains pending.
3. Confirm rules/settings remain and native hook markers are restored once. Android must become Mobile + Tablet, iPhone Mobile, iPad/Kindle/PlayBook Tablet, and Windows/Unknown Desktop. Opening a rule normalizes old values; saving persists canonical values without a schema migration. Repeat an edit/reconcile and check no duplicate markers or placements. Normal menu navigation and the direct URL must open the same rule table.
4. Check automatic Czech and preservation of custom text as described above, without importing XML. Confirm non-Czech languages still use English defaults. A later explicitly requested XML refresh must affect only this application's translations; verify the intended language after native import.
5. On the disposable test installation, uninstall. Confirm this app's tables/settings/markers are removed and existing Pages blocks still exist.

Keep stable 1.0.0 pending until installation, assets, permissions, cache isolation and a real version-to-version upgrade all pass. Before sharing a defect report, remove cookies, CSRF keys, personal data and any private block contents.

| Check | Result | Notes / reproduction |
| --- | --- | --- |
| Clean ACP install | Pending | |
| Compact ACP menu icon / rule-editor navigation | Pending | |
| CSS category placement | Pending | |
| Native hook placement | Pending | |
| Permissions / secondary groups | Pending | |
| Platforms / URL filters | Pending | |
| First / nth / all / ordering | Pending | |
| Native assets / original request | Pending | |
| Guest CDN isolation | Pending | |
| Diagnostics / ACP restrictions | Pending | |
| Earlier beta → beta.7 upgrade / platform conversion | Pending | |
| Automatic Czech / custom text preservation | Pending | |
| English defaults / unchanged language settings | Pending | |
| Disable / uninstall | Pending | |

## Template code in beta.5

- Upgrade beta.4 → beta.5 and retain existing block rules. Retrying a partial migration must preserve data.
- Choose custom IPS template code and use the Hello world example from README. An eligible front-end request must display Hello world without an additional extension. Save and ACP diagnostics must only validate syntax.
- Exercise both placement modes, device/group/URL filters, excluded guests, before/after, repetition and absent selectors. Code is prepared server-side before CSS target discovery.
- A restricted administrator without rqbp_manage_code must not read code or submit a forged source, enable, duplicate or edit code rules. Block management remains available.
- Custom code must work without Pages; Pages block rules must be omitted. Syntax and API failures must not leak output.

## Beta.6 security acceptance

- Purge affected CDN/reverse-proxy cache after upgrading. Check final private/no-store headers on archived topics and search, for both eligible and omitted audiences.
- Exercise different guest/bot groups and native block permissions without an explicit device filter.
- A block administrator without code permission must not globally reactivate enabled code rules; the panic stop remains available.
- Navigate while an ad script is pending: remove stale wrappers immediately and stop subsequent old scripts. An already attached external script may still execute.
