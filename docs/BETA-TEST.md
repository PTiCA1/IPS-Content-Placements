# ACP beta acceptance test

Status: **not run against a live IPS installation**. Automated mocks and browser fixtures do not establish native installation compatibility. Record results before publishing or calling the package stable.

Use a licensed, private IPS **5.0.20 + Pages** test installation with a database/file backup. Record the PHP version, IPS build, theme, browser, CDN/cache configuration and application TAR SHA256. The initial package is `1.0.0-beta.1`, build `1000001`.

## Installation and basic placement

1. Upload the TAR through ACP Applications. Confirm no installer error, the correct version, the dedicated Pages Block Placements tab and global setting.
2. Create a public Pages HTML block with a unique `RQBP TEST` heading and a link. Confirm its original native display works first.
3. Create a CSS rule for a category actually on your forum, for example `li[data-categoryID="4"]`, **after**, first match, unrestricted audience. Check it appears between categories and does not appear between topic posts. Inspect the real category ID before choosing the selector.
4. Change to **before**. Check the target/category ordering and absence of duplicated block output.
5. Create a separate native-hook rule using an available safe hook, such as the `main` hook in `core/front/global/globalTemplate`, **before** or **after**. Check it displays once with valid surrounding markup. A native hook missing from your installed version is not a selectable target.
6. Select a Pages block through the picker, then switch to the manual source and use one exact `{block="the_actual_key"}`. Both should select the same existing block. Extra HTML, two tags and other template expressions must be rejected.

## Filtering and occurrences

- Test unrestricted, Windows-only, an explicit iPhone/Android multiselect and unknown platform. IPS uses user-agent platform names, not viewport width. Merely resizing a desktop browser should not change its platform.
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

1. Save representative CSS and native-hook rules, ordering, group filters and platform settings. Export a backup of the database.
2. Upgrade to a separately versioned candidate TAR through ACP. Re-uploading this same beta does **not** establish migration compatibility.
3. Confirm all rules/settings remain and native hook markers are restored once. Repeat an edit/reconcile and check no duplicate markers or placements.
4. Check the English defaults and optional Czech import. Verify the Czech file changes only this app's strings; check intended language metadata after native import.
5. On the disposable test installation, uninstall. Confirm this app's tables/settings/markers are removed and existing Pages blocks still exist.

Keep stable 1.0.0 pending until installation, assets, permissions, cache isolation and a real version-to-version upgrade all pass. Before sharing a defect report, remove cookies, CSRF keys, personal data and any private block contents.

| Check | Result | Notes / reproduction |
| --- | --- | --- |
| Clean ACP install | Pending | |
| CSS category placement | Pending | |
| Native hook placement | Pending | |
| Permissions / secondary groups | Pending | |
| Platforms / URL filters | Pending | |
| First / nth / all / ordering | Pending | |
| Native assets / original request | Pending | |
| Guest CDN isolation | Pending | |
| Diagnostics / ACP restrictions | Pending | |
| Actual version upgrade | Pending | |
| Czech translation | Pending | |
| Disable / uninstall | Pending | |
