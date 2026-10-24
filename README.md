# Pages Block Placements

Place an existing Invision Community Pages block before or after a CSS selector or a native theme hook, with rules for platforms, groups, applications and URLs. Original standalone application, MIT licensed. [Česky](README.cs.md).

**1.0.0-beta.1 — build 1000001.** Prepared against Invision Community **5.0.20**. Installation and upgrades on a running IPS forum have **not yet been verified**. This beta is for the owner's ACP test installation; GitHub publication and a stable 1.0.0 release follow that test.

## What this release does

- Select a Pages block, or enter one exact native reference such as `{block="welcome"}`.
- Choose a CSS selector or an available native template hook, then insert before or after it.
- Select the first, a numbered, or every match (CSS) or occurrence (native hook).
- Select one or more IPS platforms. Platforms come from the IPS user-agent parser; they are not browser-width breakpoints. An unknown platform can be selected explicitly.
- Allow groups and exclude groups, including secondary memberships. An excluded membership wins.
- Optionally restrict applications and include/exclude board-relative URLs. `*` is the wildcard; an exclusion wins. The query string participates, fragments do not.
- Keep native Pages block activation, visibility and rendering through `IPS\cms\Blocks\Block::display()` in the original page request. There is no separate block-rendering HTTP request.

The application does not add or extend the native advertising system. Native hooks must exist in the installed template. CSS mode can target a category without its own hook, for example `li[data-categoryID="4"]`; replace `4` with the ID actually present on your forum. Selector placements follow the theme's HTML structure and must be checked after theme/platform updates.

## Install and try the beta

Requirements: a licensed Invision Community 5.0.20 installation with **Pages** enabled, custom applications permitted, and ACP application-management access. Use a test installation and take a database/file backup before installation or upgrade.

1. Obtain `rqblockplacements-1.0.0-beta.1.tar` and `SHA256SUMS` from `dist/` or a future GitHub release. Verify the checksum, for example `shasum -a 256 -c SHA256SUMS` from the download directory containing both release files.
2. Open **ACP → System → Applications**, choose the upload/install action and upload the **TAR**. The GitHub source ZIP is not the installable application.
3. Open the **Pages Block Placements** ACP tab. Allow access to the `rqbp_manage` permission for other administrators who need it.
4. Create a simple existing Pages HTML block, then add a placement rule. Start with one known CSS selector or native hook, the first occurrence, all platforms and no audience/URL restriction.
5. Follow the [beta acceptance checklist](docs/BETA-TEST.md). Validate the output as both an administrator and an ordinary user.

The application has a global enable switch and individual rule switches. Disable the global switch or the application to stop placements while investigating a problem. Existing Pages blocks are not removed by this app's uninstall; the app's own rules and generated hook markers are removed.

Native hooks are insertion points, not a guarantee that arbitrary block markup is valid there. Avoid placing a full block inside `head`, `title`, links, or other containers that cannot contain it. Check the surrounding HTML and the block's scripts/styles. Repeated interactive blocks may contain IDs or scripts that are designed for a single instance.

## Czech translation

The application installs English defaults. The release also includes `rqblockplacements-1.0.0-beta.1-cs.xml` (the same file is inside the TAR as `resources/lang-cs.xml`). Install the app first, then use the ACP Languages upload/new-version action for the intended Czech language. This is a translation of this application only, not a complete Czech language pack. Back up an existing language before importing; the native importer can update its language metadata. On future app updates, import the matching translation again if new strings were added.

## Rules and troubleshooting

An empty platform/group/application/include-URL list means unrestricted for that filter. Group and URL exclusions still apply. URL rules use board-relative paths, including query strings: for a board at `https://example.test/community/`, `/topic/*` matches a topic under that board. Enter one URL pattern per line. Regular expressions are not supported.

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
python3 tools/validate.py --archive dist/rqblockplacements-1.0.0-beta.1.tar
```

Tools require PHP (tested with 8.3), Python 3.10+ and Node 20+ for browser tests. `PHP_BINARY` can point to a specific PHP executable. Browser tests can use `PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH` instead of a downloaded Chromium. The builder reads the original `application/resources/lang/{en,cs}.php` arrays and generates native `data/lang.xml`, `data/installLang.json` and the Czech import XML.

The TAR has `data/application.json` at its root. File ordering, modes, ownership and timestamps are normalized; `SOURCE_DATE_EPOCH` defaults to `0`. `dist/SHA256SUMS` covers the TAR and separate Czech XML. The builder does not package Git metadata, node modules, authoring language arrays, unversioned upgrade work, or any parent workspace files. See [development and release notes](docs/DEVELOPMENT.md).

Optional read-only API contract check against a separately obtained local platform:

```sh
python3 tools/validate.py --ips-source /absolute/path/to/ips_v5.0.20
```

These checks establish syntax, metadata and selected behavior. They do not substitute for the native ACP install/upgrade and block-rendering acceptance test.

## License

[MIT](LICENSE) applies to this application's original code and documentation. Invision Community is proprietary, separately licensed software. This repository grants no license to the platform and includes none of its source, developer tools, credentials or content. The project is an independent community add-on.
