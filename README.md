# Content Placements

for Invision Community 5

[GitHub project](https://github.com/PTiCA1/IPS-Content-Placements) · [Issues](https://github.com/PTiCA1/IPS-Content-Placements/issues)

**[Beta.13 pre-release](https://github.com/PTiCA1/IPS-Content-Placements/releases/tag/v1.0.0-beta.13)** · **[Download TAR](https://github.com/PTiCA1/IPS-Content-Placements/releases/download/v1.0.0-beta.13/rqblockplacements-1.0.0-beta.13.tar)** · [SHA256SUMS](https://github.com/PTiCA1/IPS-Content-Placements/releases/download/v1.0.0-beta.13/SHA256SUMS)

Optional XML: [CS](https://github.com/PTiCA1/IPS-Content-Placements/releases/download/v1.0.0-beta.13/rqblockplacements-1.0.0-beta.13-cs.xml) · [SK](https://github.com/PTiCA1/IPS-Content-Placements/releases/download/v1.0.0-beta.13/rqblockplacements-1.0.0-beta.13-sk.xml) · [PL](https://github.com/PTiCA1/IPS-Content-Placements/releases/download/v1.0.0-beta.13/rqblockplacements-1.0.0-beta.13-pl.xml) · [DE](https://github.com/PTiCA1/IPS-Content-Placements/releases/download/v1.0.0-beta.13/rqblockplacements-1.0.0-beta.13-de.xml). The TAR already contains all four translations; separate XML downloads are optional.

Place an existing Invision Community Pages block or custom IPS template code before, inside at the start, inside at the end or after a CSS target or native theme hook, with rules for platforms, groups, applications and URLs. Original standalone application, MIT licensed. [Česky](README.cs.md).

**1.0.0-beta.13 — build 1000013.** Adds the native `head` hook for metadata-only output, without a wrapping `div`. Head and body hooks accept only inside positions; CSS head placement remains unsupported. No new database schema changes. Includes complete Czech, Slovak, Polish and German translations. Targets **Invision Community 5.0.20+**, with API and template-compiler checks against the **5.0.20** source code. Native installation, head placement and upgrade acceptance remain pending before a stable release. See [security boundaries](SECURITY.md) and the [dated audit report](docs/SECURITY-AUDIT-2026-10-25.md). Purge existing CDN/reverse-proxy cache for affected URLs after upgrading.

## What this release does

- Select a Pages block, or enter one exact native reference such as `{block="my_new_block"}`. A block with that key must exist; otherwise replace `my_new_block` with your block's actual key.
- Choose a CSS selector or select a native hook through application → template → hook, then choose before, inside at the start, inside at the end or after it.
- Use the native `head` hook for `script`, `style`, `meta` and `link` elements, with inside-start/end only.
- Select the first, a numbered, or every match (CSS) or occurrence (native hook).
- Select Mobile, Tablet and/or Desktop. An empty selection permits all three. Device classes use the IPS platform and user-agent string, not browser-width breakpoints.
- Allow groups and exclude groups, including secondary memberships. An excluded membership wins.
- Optionally restrict applications and include/exclude board-relative URLs. `*` is the wildcard; an exclusion wins. The query string participates, fragments do not.
- Keep native Pages block activation, visibility and rendering through `IPS\cms\Blocks\Block::display()` in the original page request. There is no separate block-rendering HTTP request.

The application does not add or extend the native advertising system. Native hooks must exist in the installed template. CSS mode can target a category without its own hook, for example `li[data-categoryID="4"]`; replace `4` with the ID actually present on your forum. Selector placements follow the theme's HTML structure and must be checked after theme/platform updates.

The four positions apply to both CSS and native-hook rules where the context permits. Before/after insert a sibling; `inside-start` and `inside-end` insert the first or last child. Native `head` and `body` hooks permit only the two inside positions. CSS can target body inside, but cannot place content in head. The native-hook picker reads the current catalog of enabled front-end templates; `title` and unsupported hooks within head are omitted even if the native IPS editor lists them. Position labels use the installed IPS core translations. Check that your block's HTML is valid at the chosen target.

## Custom IPS template code

Choose **Custom IPS template code** as the content source, for example:

```html
{expression="'Hello world'"}
```

This example displays **Hello world** and requires no additional extension. The separate `rqbp_manage_code` permission belongs only to trusted administrators: template expressions execute server-side PHP. Superadministrators have access automatically. Pages block selection and native block permissions remain available.

Saving and ACP diagnostics validate syntax without invoking the expression. Rendering happens on the front end after rule filters pass. Use the same device, group, URL and selector/hook settings as for blocks. Custom code has no implicit Pages block permissions. Matching routes bypass shared page caching. Enter IPS template syntax rather than `<?php ?>` tags.

## Native head placement

Choose the native hook mode, application Core, template `core/front/global/globalTemplate`, hook `head`, and `inside-start` or `inside-end`. This head support is exclusive to native hooks; CSS targeting head remains unsupported. For example, custom IPS template code can return a simple metadata element:

```html
<meta name="rqbp-test" content="head-placement">
```

Both Pages blocks and custom template code can supply head content, but the actual rendered fragment must contain only `script`, `style`, `meta` and `link` elements. It is inserted without a `div` wrapper. Visible HTML/text, a wrapper added by a block, or other unsupported output causes the entire placement to be omitted. The Hello world example above is visible body content and does not qualify for head placement.

ACP save and diagnostics do not render a Pages block or execute template expressions, so they cannot validate their final output. The metadata-fragment check happens during the public request, after rule eligibility and native block permissions. Check the resulting page source and diagnostics on your test installation.

## Install and try the beta

Requirements: licensed Invision Community 5.0.20+, custom applications permitted, and ACP application-management access. Pages is required for block rules; custom template rules can run without it. Use a test installation and a database/file backup.

Upgrade the existing application rather than installing a second copy. The internal key `rqblockplacements` and release filename prefix remain unchanged. The upgrade refreshes the application's standard names while preserving administrator-customized text, rules and settings. Beta.13 has no new schema migration. When upgrading an older beta, beta.12 still widens `rqbp_rules.rule_position` from `VARCHAR(8)` to `VARCHAR(16)` without rewriting existing before/after values; that migration can be retried after a partial upgrade.

1. Download [rqblockplacements-1.0.0-beta.13.tar](https://github.com/PTiCA1/IPS-Content-Placements/releases/download/v1.0.0-beta.13/rqblockplacements-1.0.0-beta.13.tar) from the [beta.13 pre-release](https://github.com/PTiCA1/IPS-Content-Placements/releases/tag/v1.0.0-beta.13), or use the file in `dist/`. To verify with `shasum -a 256 -c SHA256SUMS`, keep `SHA256SUMS` and all four translation XML files in the same directory as the TAR. Installation itself requires only the TAR.
2. Open **ACP → System → Applications**, choose the upload/install action and upload the **TAR**. The GitHub source ZIP is not the installable application.
3. Open **ACP → Content Placements → Placement rules**. This is the application's own tab near the bottom of the sidebar, not an entry under native Advertisements or Pages. The standard main tab name is **Content Placements** in every language; for example, the Czech submenu is **Pravidla umístění**. Allow access to the `rqbp_manage` permission for other administrators who need it.
4. Create a simple existing Pages HTML block, then add a placement rule. Start with one known CSS selector or native hook, the first occurrence, all platforms and no audience/URL restriction.
5. Follow the [beta acceptance checklist](docs/BETA-TEST.md). Validate the output as both an administrator and an ordinary user.

If the menu is hard to find, open your existing ACP `index.php` address and replace only its query string with `?app=rqblockplacements&module=placements&controller=rules`. Keep the actual ACP directory from your current address, including a renamed ACP folder; do not replace it with a guessed `/admin/` path. The global switch is under **Content Placements → Settings**. In beta.1 the missing icon made the new bottom tab look blank on compact sidebars; the direct URL works independently of that icon.

The application has a global enable switch and individual rule switches. Disable the global switch or the application to stop placements while investigating a problem. Existing Pages blocks are not removed by this app's uninstall; the app's own rules and generated hook markers are removed.

Native hooks are insertion points; the block's markup must also suit the chosen context. The catalog excludes unsupported contexts; native `head` and `body` accept only inside positions, and head additionally requires metadata-only output. Check the surrounding HTML and the block's scripts/styles. Repeated interactive blocks may contain IDs or scripts that are designed for a single instance.

## Czech, Slovak, Polish and German translations

Each translation covers all application strings. Installation and ACP upgrades fill missing translations in existing languages according to their locale:

| Language | Recognized locale examples |
| --- | --- |
| Czech | `cs`, `cs_CZ`, `cs-CZ`, `Czech_…` |
| Slovak | `sk`, `sk_SK`, `sk-SK`, `Slovak_…` |
| Polish | `pl`, `pl_PL`, `pl-PL`, `Polish_…` |
| German | `de`, `de_DE`, `de-DE`, `German_…` |

No separate XML import is required for these existing languages. Custom text, including an intentional empty value, is preserved. Upgrades fill missing keys; changing an existing translated value requires an explicit refresh. Beta.9 also renames the exact known standard product labels from earlier betas; different custom labels stay unchanged. Other languages keep English defaults. The application does not create a language, switch the site's default language or change language metadata.

The release also provides optional `rqblockplacements-1.0.0-beta.13-cs.xml`, `-sk.xml`, `-pl.xml` and `-de.xml` files, bundled in the TAR as `resources/lang-{cs,sk,pl,de}.xml`. Use the appropriate XML in ACP Languages for an explicit translation refresh or a language added after application installation. These files translate only this application. Back up the intended language before an optional native XML import, whose metadata behavior belongs to IPS.

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
python3 tools/validate.py --archive dist/rqblockplacements-1.0.0-beta.13.tar
```

Tools require PHP (tested with 8.3), Python 3.10+ and Node 20+ for browser tests. `PHP_BINARY` can point to a specific PHP executable. Browser tests can use `PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH` instead of a downloaded Chromium. The builder reads the original `application/resources/lang/{en,cs,sk,pl,de}.php` arrays and generates native `data/lang.xml`, `data/installLang.json` and all four translation XML files.

The TAR has `data/application.json` at its root. File ordering, modes, ownership and timestamps are normalized; `SOURCE_DATE_EPOCH` defaults to `0`. `dist/SHA256SUMS` covers the TAR and the four separate translation XML files. The installer reads the bundled XML translations; Git metadata, node modules, authoring language arrays, unversioned upgrade work and parent workspace files remain excluded. See [development and release notes](docs/DEVELOPMENT.md).

Optional read-only API contract check against a separately obtained local platform:

```sh
python3 tools/validate.py --ips-source /absolute/path/to/ips_v5.0.20
```

These checks establish syntax, metadata and selected behavior. They do not substitute for the native ACP install/upgrade and block-rendering acceptance test.

## Support and bug reports

Use [GitHub Issues](https://github.com/PTiCA1/IPS-Content-Placements/issues) for ordinary bugs and feature requests. Include the application, IPS, PHP and theme versions, reproduction steps and redacted logs. Remove credentials, cookies, CSRF tokens and private content. Report suspected security vulnerabilities privately as described in [SECURITY.md](SECURITY.md).

## License

[MIT](LICENSE) applies to this application's original code and documentation. Invision Community is proprietary, separately licensed software. This repository grants no license to the platform and includes none of its source, developer tools, credentials or content. The project is an independent community add-on.
