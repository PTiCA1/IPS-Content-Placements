# Pages Block Placements

Place an existing Invision Community Pages block or custom IPS template code before or after a CSS selector or a native theme hook, with rules for platforms, groups, applications and URLs. Original standalone application, MIT licensed. [Česky](README.cs.md).

**1.0.0-beta.6 — build 1000006.** Security fixes for response caching and permissions, refactoring and regression tests against IPS **5.0.20**. See [security boundaries](SECURITY.md) and the [audit report](docs/SECURITY-AUDIT-2026-10-25.md). Purge existing CDN/reverse-proxy cache for affected URLs after upgrading. Live acceptance and GitHub publication remain pending.

## What this release does

- Select a Pages block, or enter one exact native reference such as `{block="welcome"}`.
- Choose a CSS selector or an available native template hook, then insert before or after it.
- Select the first, a numbered, or every match (CSS) or occurrence (native hook).
- Select Mobile, Tablet and/or Desktop. An empty selection permits all three. Device classes use the IPS platform and user-agent string, not browser-width breakpoints.
- Allow groups and exclude groups, including secondary memberships. An excluded membership wins.
- Optionally restrict applications and include/exclude board-relative URLs. `*` is the wildcard; an exclusion wins. The query string participates, fragments do not.
- Keep native Pages block activation, visibility and rendering through `IPS\cms\Blocks\Block::display()` in the original page request. There is no separate block-rendering HTTP request.

The application does not add or extend the native advertising system. Native hooks must exist in the installed template. CSS mode can target a category without its own hook, for example `li[data-categoryID="4"]`; replace `4` with the ID actually present on your forum. Selector placements follow the theme's HTML structure and must be checked after theme/platform updates.

## Custom IPS template code

Choose **Custom IPS template code** as the content source, for example:

```html
{expression="(new \IPS\MafraApi())->getMafraApiId('28')" raw="true"}
```

This example requires your own `IPS\MafraApi` class on the forum. The separate `rqbp_manage_code` permission belongs only to trusted administrators: template expressions execute server-side PHP. Superadministrators have access automatically. Pages block selection and native block permissions remain available.

Saving and ACP diagnostics validate syntax without invoking the expression. Rendering happens on the front end after rule filters pass. Use the same device, group, URL and selector/hook settings as for blocks. Custom code has no implicit Pages block permissions. Matching routes bypass shared page caching. Enter IPS template syntax rather than `<?php ?>` tags.

## Install and try the beta

Requirements: licensed IPS 5.0.20, custom applications permitted, and ACP application-management access. Pages is required for block rules; custom template rules can run without it. Use a test installation and a database/file backup.

1. Obtain `rqblockplacements-1.0.0-beta.6.tar` and `SHA256SUMS` from `dist/` or a future GitHub release. Verify the checksum, for example `shasum -a 256 -c SHA256SUMS` from the download directory containing both release files.
2. Open **ACP → System → Applications**, choose the upload/install action and upload the **TAR**. The GitHub source ZIP is not the installable application.
3. Open **ACP → Block placements → Placement rules**. This is the application's own tab near the bottom of the sidebar, not an entry under native Advertisements or Pages. When using an existing Czech language the path is **Umístění bloků → Pravidla umístění**, without a separate XML import. Allow access to the `rqbp_manage` permission for other administrators who need it.
4. Create a simple existing Pages HTML block, then add a placement rule. Start with one known CSS selector or native hook, the first occurrence, all platforms and no audience/URL restriction.
5. Follow the [beta acceptance checklist](docs/BETA-TEST.md). Validate the output as both an administrator and an ordinary user.

If the menu is hard to find, open your existing ACP `index.php` address and replace only its query string with `?app=rqblockplacements&module=placements&controller=rules`. Keep the actual ACP directory from your current address, including a renamed ACP folder; do not replace it with a guessed `/admin/` path. The global switch is under **Block placements → Settings**. In beta.1 the missing icon made the new bottom tab look blank on compact sidebars; the direct URL works independently of that icon.

The application has a global enable switch and individual rule switches. Disable the global switch or the application to stop placements while investigating a problem. Existing Pages blocks are not removed by this app's uninstall; the app's own rules and generated hook markers are removed.

Native hooks are insertion points, not a guarantee that arbitrary block markup is valid there. Avoid placing a full block inside `head`, `title`, links, or other containers that cannot contain it. Check the surrounding HTML and the block's scripts/styles. Repeated interactive blocks may contain IDs or scripts that are designed for a single instance.

## Czech translation

The TAR includes Czech application text and applies it automatically on installation and upgrade to existing languages with Czech locale codes such as `cs_CZ`, `cs-CZ` or `Czech_…`. No separate XML import is required. Existing custom text, including an intentional empty value, is preserved. Upgrades fill new keys; changing an existing translated value requires the optional XML refresh. Other languages keep the English defaults; the application does not switch the site's default language or change language metadata.

The release also includes `rqblockplacements-1.0.0-beta.6-cs.xml` (inside the TAR as `resources/lang-cs.xml`) for an explicit translation refresh or a Czech language added after the application was installed. Use ACP Languages to import it into the intended language. This file translates only this application. Back up the language before an optional native XML import, whose metadata behavior belongs to IPS.

## Rules and troubleshooting

An empty platform/group/application/include-URL list means unrestricted for that filter. Group and URL exclusions still apply. URL rules use board-relative paths, including query strings: for a board at `https://example.test/community/`, `/topic/*` matches a topic under that board. Enter one URL pattern per line. Regular expressions are not supported.

Android user agents containing `Mobile` are Mobile; Android without it, iPad and Kindle are Tablet. Other or unrecognized devices are Desktop. Resizing the browser does not change the class, and an iPad using a desktop user agent may be indistinguishable from Desktop. Existing OS selections are normalized on read: Android → Mobile + Tablet, iPhone → Mobile, iPad/Kindle/PlayBook → Tablet, PC/unknown → Desktop. Saving the rule persists the new values; no schema migration is required.

Hook occurrence means a hook invocation during the current server request. A repeated row template may produce many invocations. CSS occurrence means a matching target in the document; inserted placement nodes are not targets. Rules are ordered by their configured order and ID. Missing blocks, missing Pages, unavailable hooks/selectors or failed permission checks must produce no visitor-facing error output; inspect the rule diagnostics and IPS logs if a block is absent.

Guest pages that may differ by placement platform/audience must not share a CDN cache variant. The runtime suppresses page caching for eligible placement routes. Purge any existing affected guest-page cache when changing rules, and test your CDN separately. The app caches rule configuration, not cross-user rendered block HTML. Native blocks retain their own IPS behavior.

## Build and checks

The public source tree contains only this app's original files. The IPS platform and developer tools are never bundled. A configured IPS database is not needed to create the TAR; a real installation is needed to verify it.

```sh
npm ci
npm run test:php
npm run test:build
npx playwright install chromium
npm run test:browser
python3 tools/build.py --check-reproducible
python3 tools/validate.py --archive dist/rqblockplacements-1.0.0-beta.6.tar
```

Tools require PHP (tested with 8.3), Python 3.10+ and Node 20+ for browser tests. `PHP_BINARY` can point to a specific PHP executable. Browser tests can use `PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH` instead of a downloaded Chromium. The builder reads the original `application/resources/lang/{en,cs}.php` arrays and generates native `data/lang.xml`, `data/installLang.json` and the Czech import XML.

The TAR has `data/application.json` at its root. File ordering, modes, ownership and timestamps are normalized; `SOURCE_DATE_EPOCH` defaults to `0`. `dist/SHA256SUMS` covers the TAR and separate Czech XML. The installer reads the bundled `resources/lang-cs.xml`; Git metadata, node modules, authoring language arrays, unversioned upgrade work and parent workspace files remain excluded. See [development and release notes](docs/DEVELOPMENT.md).

Optional read-only API contract check against a separately obtained local platform:

```sh
python3 tools/validate.py --ips-source /absolute/path/to/ips_v5.0.20
```

These checks establish syntax, metadata and selected behavior. They do not substitute for the native ACP install/upgrade and block-rendering acceptance test.

## License

[MIT](LICENSE) applies to this application's original code and documentation. Invision Community is proprietary, separately licensed software. This repository grants no license to the platform and includes none of its source, developer tools, credentials or content. The project is an independent community add-on.
