# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.2] - 2026-09-18

### Security
- Font uploads are now checked by content, not only by file name. The first four bytes
  must be the font signature that matches the file extension: `wOF2` for `.woff2`,
  `wOFF` for `.woff`, `0x00010000` / `true` / `ttcf` for `.ttf`, and `OTTO` or
  `0x00010000` for `.otf`. Previously any content that libmagic reported as
  `application/octet-stream` or as another font type was accepted as long as the
  extension was allowed. Admin-only endpoint, so the practical risk was low.

### Added
- New validation message `upload.invalid_signature` (en/ko).
- Unit and feature tests under `tests/` (excluded from release archives via `.gitattributes`).

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
