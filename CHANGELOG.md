# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.1] - 2026-09-16

### Fixed
- CFF-flavoured OTF files (detected as `application/vnd.ms-opentype`) were rejected by the upload validator.

## [1.0.0] - 2026-09-08

### Added
- Initial public release.
- Apply one custom font site-wide (admin + front-end) by overriding the Tailwind v4
  `--font-sans` token through the `core.assets.custom_assets` filter — no template
  source changes required.
- Two font sources, selectable in the admin settings screen:
  - **Upload** — upload an `otf` / `ttf` / `woff` / `woff2` file; stored on the
    plugin storage disk and served through a streaming route, with an `@font-face`
    rule generated automatically.
  - **Webfont CSS** — paste a webfont CSS snippet (an `@font-face` block, e.g. from
    noonnu, or an `@import url('...');` for services like Google Fonts); injected
    verbatim.
- Graceful fallback: if no font is configured, the CSS fails to load, or the font
  family name does not match the CSS, the site falls back to its default font.
- Deactivating the plugin removes the override automatically.
