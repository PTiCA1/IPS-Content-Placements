# Security boundaries

Project: [PTiCA1/IPS-Content-Placements](https://github.com/PTiCA1/IPS-Content-Placements). Use [Issues](https://github.com/PTiCA1/IPS-Content-Placements/issues) for ordinary bugs, not public disclosure of suspected vulnerabilities.

The application places administrator-selected content. The `rqbp_manage_code` permission intentionally permits native IPS template expressions and therefore server-side PHP execution. It is a code-administration permission, not a sandbox. Grant it only to people trusted to run code on the forum server. Pages block visibility continues to use native Pages permissions; custom template code uses its rule's audience filters and the integration's own authorization.

`RuleAccess` centralizes code permissions for editing, actions, reordering and global activation. Block administrators can stop all placements; reactivating enabled code rules requires code permission. `PageCache` prevents shared storage on every eligible active-rule route before audience evaluation. It also overrides earlier public response headers. Changing these boundaries requires regression tests.

Names and selectors are data. Server HTML is JSON-encoded with hex escaping for CSS transport. Diagnostics use text nodes and do not expose template source to visitors. Rendered HTML and scripts are trusted content from an allowed Pages block or code administrator; the client does not sanitize them as untrusted member posts.

Already attached external scripts may execute after a browser navigation or element removal. The application removes stale placements, cancels its waits and stops subsequent scripts, but cannot revoke code already handed to the browser.

The builder packages only the files declared in `tools/package-files.json`, plus the original MIT license. Runtime rules, private forum configuration, licensed IPS sources and development dependencies must never be included.

See the [2026-10-25 audit and fixed findings](docs/SECURITY-AUDIT-2026-10-25.md). A code review and automated tests reduce risk; they cannot establish that no unknown vulnerabilities exist. The actual forum deployment, third-party integrations and CDN configuration require separate validation. Report suspected security issues privately to the project maintainer or affected forum owner using a private contact method they provide. Do not post credentials, private content or details of a production exposure in public Issues.
