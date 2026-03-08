# Changelog — Email Signatures

All notable changes to this project are documented in this file.
Format based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
Versioning follows [Semantic Versioning](https://semver.org/).

---

## [1.3.0] — 2025-03-08

### Added
- **Signature preview**: new "Preview" button on the user profile tab opens the
  final rendered PNG in a modal before downloading or sending. Uses `download.php?preview=1`
  for inline display.
- **Clickable variable badges**: `{nombre}`, `{empresa}`, `{fecha}` in the email
  configuration panel are now clickable — click inserts the variable at the cursor
  position in the last focused field (subject, body, or footer).
- **Unsaved positions indicator**: the Positions tab shows an orange dot and a warning
  banner when field positions have been modified but not yet saved.
- **`plugin_signatures_update()`** in `setup.php`: ensures installations upgrading from
  older versions receive new configuration keys (position defaults) without needing a
  full reinstall.
- **`PluginSignaturesConfig` class** (`inc/config.class.php`): centralizes all access to
  `glpi_configs` for the plugin. Caches the config array for the duration of the HTTP
  request; `invalidate()` is called after saving to guarantee fresh reads.
- Sending feedback on "Download" and "Send" buttons: spinner replaces the icon and the
  button is disabled on click to prevent double submission.

### Fixed
- `Content-Disposition` header in `download.php` now includes `filename*=UTF-8''...`
  (RFC 6266) for correct handling of non-ASCII file names on all clients.
- `catch (Throwable)` blocks in `send.php`, `send_test.php`, and `download.php` now
  call `Toolbox::logError()` so errors are traceable in the GLPI log instead of being
  silently discarded.

### Changed
- All `Config::getConfigurationValues('plugin_signatures')` calls across the plugin
  now go through `PluginSignaturesConfig::getAll()` / `::get()`.
- `plugin.xml` descriptions updated to reflect v1.3 features in all four languages.

---

## [1.2.0] — 2025-03-08

### Added
- **Visual position editor** (Positions tab in plugin config): drag-and-drop each
  signature field over the actual PNG template at 1:1 pixel scale, using the real
  Avenir TTF fonts rendered via `@font-face`. Positions and font sizes are stored in
  `glpi_configs` — no PHP editing required.
- **`plugin_signatures_getDefaults()`** in `setup.php`: single source of truth for all
  default coordinate and font-size values for both templates (with/without mobile).
- `plugin_signatures_install()` initializes position keys on first install only
  (uses `array_diff_key` to never overwrite existing config).
- `plugin_signatures_uninstall()` now deletes all keys returned by `getDefaults()`
  (previously only deleted the five general keys).
- **Restore defaults** button per template in the position editor.
- Per-field font size inputs in the position editor table with live position display.
- `PluginSignaturesSignature::sanitizeFilename()` static method: eliminates the
  three-way duplication of filename sanitization logic across `download.php`, `send.php`,
  and `send_test.php`.
- `PluginSignaturesSignature::buildEmailHtml()` static method: eliminates the
  duplication of dynamic variable replacement, bold markdown processing, and HTML
  envelope construction between `send.php` and `send_test.php`. Accepts an `$isTest`
  flag for the test-email warning banner.

### Fixed
- **`hasBase` logic** in `user.class.php`: previously required *both* templates to
  enable the download/send buttons for any user. Now checks only the template
  relevant to the user (`base1` if they have a mobile number, `base2` otherwise).
- **`RuntimeException` from `generatePNG()`** was unhandled in `download.php` and
  `send.php`, causing a blank screen on invalid entity. Both endpoints now wrap the
  call in `try/catch` and show a GLPI error message.
- **`include_qr` logic in `send_test.php`**: was incorrectly derived from the
  WhatsApp country code config key. Now correctly based on whether the current admin
  user has a mobile number, matching the behavior of `download.php`.
- **CRLF line endings** in `download.php`, `resource.send.php`, and
  `paths.class.php` — normalized to LF.
- `plugin_signatures_check_prerequisites()` now validates PHP ≥ 8.1 and the GD
  extension, providing actionable error messages before installation instead of
  failing at runtime.
- Minor: inconsistent indentation of `'version'` key in `plugin_version_signatures()`.

### Changed
- `plugin_signatures_uninstall()` derives the key list from `getDefaults()` instead
  of a hardcoded array.
- `plugin.xml` updated to include the `1.2.0` release entry.
- `.gitattributes` added to enforce LF line endings across all text files.
- `signature.class.php` reads all field coordinates from `glpi_configs` (via
  `PluginSignaturesConfig::getAll()`) with per-key fallback to hardcoded defaults.

---

## [1.1.0] — 2025-03-07

### Added
- **Email delivery**: new "Send by email" button on the user profile tab sends the
  generated PNG as an attachment via GLPI's outgoing mail system (`GLPIMailer`).
- **Email configuration** (new section in the General tab):
  - Subject, body, and footer fields with dynamic variable support:
    `{nombre}` (user full name), `{empresa}` (entity name), `{fecha}` (current date).
  - `**bold**` markdown support in body and footer rendered as
    `<span style="font-weight:bold">` for broad email client compatibility.
  - `{empresa}` resolved from the user's entity, with fallback to `$CFG_GLPI['name']`
    for the root entity (id = 0).
- **Test email button**: sends a preview email to the current admin's registered
  address. Includes a yellow warning banner in the email body to distinguish it from
  production sends. Endpoint: `front/send_test.php` (POST only, CSRF validated
  automatically by GLPI's `CheckCsrfListener`).
- New `front/send.php` and `front/send_test.php` endpoints.
- `PluginSignaturesSignature::checkEmailConfig()` static method for validating that
  subject and body are configured before allowing email sends.
- Internationalization expanded to `en_GB` locale (previously only `es_MX`, `en_US`,
  `fr_FR`). All `.po`/`.mo` files regenerated.

### Fixed
- Logo PNGs (`logo.png`, `logo_small.png`) regenerated with proper RGBA transparent
  background matching GLPI's official plugin style (white card + gold flap on
  transparent backing).
- `banner.png` regenerated using Avenir fonts from the plugin's own `fonts/` directory
  for visual consistency.

### Changed
- Plugin folder renamed from `signatures_v2` to `signatures`.
- `PluginSignaturesSignature::buildEmailHtml()` uses inline `<span>` style for bold
  instead of `<strong>` for compatibility with Outlook and legacy mail clients.

---

## [1.0.0] — 2025-02-26

### Added
- Initial release.
- Generates personalized PNG email signatures per GLPI user from a configured
  background template, overlaying text with PHP GD and Avenir TTF fonts.
- Two configurable PNG templates: `base.png` (with mobile) and `base2.png` (without
  mobile). Template selection is automatic based on the user's `mobile` field.
- Fields rendered on the signature:
  - Full name (`getFriendlyName()`) with automatic font size reduction (40px → 20px)
    to prevent overflow.
  - Title / position (`glpi_usertitles` via `usertitles_id`).
  - Primary email address (`glpi_useremails`, `is_default = 1`).
  - Mobile number, entity phone, extension or office phone (dynamic layout).
  - Facebook page name and corporate website (from the user's entity).
- Optional WhatsApp QR code generated with TCPDF (bundled with GLPI), placed at a
  fixed position on the template.
- Configuration page (plugin config tab):
  - Facebook page name field.
  - WhatsApp country code (numeric, no `+`).
  - Template upload/preview/delete for both templates (PNG, max 300 KB, MIME
    validation).
- Dedicated "Email Signature" tab on each GLPI user profile (via `CommonGLPI` tab
  registration).
- Access control: users can download their own signature; `config UPDATE` right
  required to download another user's signature.
- `front/download.php`: generates PNG, streams it as a download, and deletes the
  temporary file.
- `front/resource.send.php`: serves template PNGs to the browser (requires
  `config READ` right).
- `inc/paths.class.php`: centralizes all physical paths and URLs.
- `inc/signature.class.php`: generation logic with `generatePNG()` and
  `checkRequirements()`.
- `inc/user.class.php`: GLPI tab registration and UI.
- Internationalization: `es_MX`, `en_US`, `fr_FR` locales with compiled `.mo` files.
- `plugin.xml` for GLPI marketplace submission.
- GLPI 11.0+ compatibility (`Plugin::registerClass`, `Session::checkLoginUser`,
  Bootstrap 5 card/ribbon UI patterns).
