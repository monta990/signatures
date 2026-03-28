# Changelog — Email Signatures

All notable changes to this project are documented in this file.
Format based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
Versioning follows [Semantic Versioning](https://semver.org/).

---

## [1.5.0] — 2026-03-22

### Added
- **Custom font upload**: administrators can now upload TTF and OTF font files (max. 2 MB)
  from the new **Fonts** tab in the plugin configuration page. Uploaded fonts are stored in
  `GLPI_PLUGIN_DOC_DIR/signatures/fonts/` and survive plugin updates.
- **Per-role font selection**: two dropdowns (**Bold font** and **Regular font**) let
  administrators choose which font to use for the name field and all other fields
  respectively. Selecting "Built-in (Avenir)" always falls back to the bundled Avenir fonts.
- **Font validation via magic bytes**: uploaded files are validated against known TTF/OTF
  magic byte signatures (`00 01 00 00`, `true`, `OTTO`) before being saved, preventing
  disguised non-font files regardless of extension or MIME type reported by the browser.
- **Font serving endpoint** (`resource.send.php?resource=font&name=`): serves user-uploaded
  fonts to the browser for live `@font-face` preview in the position editor.
- **Installed fonts table**: lists all uploaded fonts with their active role badge (Bold /
  Regular) and a per-font delete button with its own CSRF token.
- **`PluginSignaturesPaths::resolvedFontBold/Regular()`**: resolve the active font path
  at generation time — user font if configured and readable, built-in Avenir otherwise.
- **`PluginSignaturesSignature::validateFontFile()`**: magic-byte validation for uploaded fonts.
- **`PluginSignaturesSignature::sanitizeFontFilename()`**: sanitizes uploaded font filenames
  to ASCII-safe names before writing to disk.
- 24 new translatable strings added to all locales (es_MX, en_US, en_GB, fr_FR).

---

## [1.4.0] — 2026-03-22

### Changed
- **Base language changed from Spanish to English**: the plugin's `msgid` strings are now
  English. Previously all `__()` calls used Spanish as the source string, meaning a GLPI
  instance without any of the bundled locales would display Spanish. Now the fallback
  language is English, consistent with GLPI plugin conventions.
  - All PHP files updated: `__('Cadena en español', 'signatures')` →
    `__('English string', 'signatures')`.
  - `signatures.pot` rebuilt with English msgids (118 strings; 6 orphaned entries removed).
  - `es_MX.po` — msgids updated to English; Spanish `msgstr` values preserved.
  - `en_US.po` — msgids updated to English; `msgstr` entries left empty (base language).
  - `en_GB.po` / `fr_FR.po` — msgids updated to English; existing translations preserved.
- **License upgraded from GPL v2+ to GPL v3+**: aligns with GLPI's own license.
  `LICENSE` file replaced with the official GPL v3 text. Updated in `setup.php`,
  `plugin.xml`, and `README.md`.

---

## [1.3.4] — 2026-03-22

### Fixed
- **`inc/paths.class.php` — missing `GLPI_ROOT` guard**: added the standard
  `if (!defined('GLPI_ROOT')) die(...)` guard. It was the only file in `inc/` without
  it, leaving the class directly accessible via HTTP.
- **`inc/paths.class.php` — inconsistent indentation**: `pluginDir()` and the font/URL
  methods used 4-space indent while the rest of the file used 3-space (GLPI style).
  All methods now use 3-space indent uniformly.
- **`inc/paths.class.php` — typo in docblock**: `"si esta en plugins"` corrected to
  `"si está en plugins"` (missing accent).
- **`front/resource.send.php` — hardcoded untranslatable strings**: `exit('Recurso
  inválido')` and `exit('No encontrado')` replaced with `exit(__(..., 'signatures'))`.
  Both strings added to POT and all locale files (es_MX, en_US, en_GB, fr_FR).
- **`front/download.php` — unreliable `HTTP_REFERER` fallback**: error redirects now
  use a computed URL (`User::getFormURLWithID()` + `forcetab`) instead of the
  `HTTP_REFERER` header, which can be absent when browsers enforce referrer policies.
- **`front/send.php` — unreliable `HTTP_REFERER` fallback**: same fix applied in normal
  send mode. Test mode keeps `HTTP_REFERER` as fallback since it always originates from
  the config page (no userid available there).
- **Log prefix inconsistency**: `download.php` used an em-dash (`–`) in
  `'signatures plugin – generatePNG'` while `send.php` used a hyphen (`-`). Both now
  use a hyphen consistently.
- **`inc/signature.class.php` — direct call to global function**: `generatePNG()` called
  `plugin_signatures_getDefaults()` (global function from `setup.php`) directly, bypassing
  `PluginSignaturesConfig`. Replaced with `PluginSignaturesConfig::getDefaults()`, which
  delegates to `setup.php` as the single source of truth. Added `getDefaults()` static
  method to `inc/config.class.php`.
- **`inc/user.class.php` — non-standard tab key**: `getTabNameForItem()` returned an
  associative key `['signatures' => ...]`. GLPI expects integer keys; changed to
  `[1 => ...]`.

### Changed
- **Upload validation simplified**: removed the non-blocking dimension warning that
  fired when a template's width or height fell outside the 400–2000 × 100–800 px
  range. The only hard server-side limit is now **file size (300 KB)**. MIME type
  validation (PNG only) is still enforced and remains a blocking check.
- **Recommended template dimensions updated to 650×250 px**: all UI hints, README,
  and documentation previously referenced ~650×216 px. The new recommended size is
  **650×250 px**, displayed as a non-blocking helper text below each template upload
  input ("Solo PNG · Máx. 300 KB · Dimensiones recomendadas: 650×250 px").

---

## [1.3.3] — 2026-03-14

### Changed
- **Upload validation simplified**: removed the non-blocking dimension warning that
  fired when a template's width or height fell outside the 400–2000 × 100–800 px
  range. The only hard server-side limit is now **file size (300 KB)**. MIME type
  validation (PNG only) is still enforced and remains a blocking check.
- **Recommended template dimensions updated to 650×250 px**: all UI hints, README,
  and documentation previously referenced ~650×216 px. The new recommended size is
  **650×250 px**, displayed as a non-blocking helper text below each template upload
  input ("Solo PNG · Máx. 300 KB · Dimensiones recomendadas: 650×250 px").

---

## [1.3.2] — 2026-03-13

### Added
- **Inline text formatting in email fields**: the email body and footer now support
  a lightweight markdown-style syntax rendered as inline CSS styles (Outlook/Gmail
  compatible):
  - `**texto**` → **bold** (`font-weight:bold`)
  - `*texto*` → *italic* (`font-style:italic`)
  - `__texto__` → underline (`text-decoration:underline`)
  Formats can be combined (e.g. `**__bold underline__**`).
- **Format toolbar (B / I / U)**: a three-button toolbar appears above the Body and
  Footer textareas. Clicking a button wraps the selected text in the corresponding
  markers, or inserts a placeholder word if nothing is selected.
- **Editable X/Y position inputs**: the Posición column in the position editor table
  now renders two independent numeric inputs (X and Y) per field instead of a
  read-only text label. Typing a value moves the corresponding canvas element
  immediately, keeping drag and manual entry always in sync.
- **Placeholder labels on X/Y inputs**: each position input carries
  `placeholder="X"` or `placeholder="Y"` so their purpose is clear at a glance.
- **Active-tab persistence after save**: the form tracks the currently visible tab
  via a hidden `active_tab` input. After saving, the page redirects back to the same
  tab using a URL hash (`#tab-positions`, `#tab-cel`, etc.) and Bootstrap Tab API
  activates it on load.
- **`.gitignore`**: ignores OS artifacts (`.DS_Store`, `Thumbs.db`), editor
  directories, zip files, and the `templates/` directory.

### Fixed
- **Drag boundary clamping**: fields can no longer be dragged outside the template
  image. `onMove()` now computes the maximum allowed CSS position as
  `img.clientWidth - el.offsetWidth` (and the Y equivalent), so the limit accounts
  for the element's own size and adapts to any template resolution or viewport width.
  Previously only the minimum (0) was enforced.
- **Manual input boundary clamping**: the same boundary logic is applied when the
  user types into the X or Y inputs. Maximum GD coordinates are pre-calculated by
  `applyScale()` and stored in `el.dataset` so they remain available even when the
  canvas tab is hidden (`clientWidth = 0`).
- **JS broken by `<?= ?>` inside heredoc**: `SIG_I18N` and `SIG_USER_I18N` objects
  were rendered as literal PHP tags inside `echo <<<HTML` blocks, corrupting the
  entire script. All translated strings are now pre-computed as PHP variables before
  the heredoc and interpolated via `{$_var}` syntax.
- **Race condition in `generatePNG()`**: the temporary PNG file is now named with
  `uniqid('', true)` in addition to the user ID, ensuring concurrent requests each
  get their own file.
- **Server-side position sanitization**: coordinate values received via POST are now
  clamped to `0–9999` (X/Y) and `1–200` (font size) before being stored in DB.
- **Reset confirmation dialog**: clicking "Restaurar posiciones por defecto" now
  shows a `confirm()` prompt before applying the reset. The prompt text is fully
  translatable via the i18n system.
- **`resource.send.php` HTTP caching**: emits `Last-Modified` and `ETag` headers
  and returns `304 Not Modified` when the client already has the current version.
- **`signature.class.php` fallback coordinates**: the `$p()` lambda now reads
  missing config values from `plugin_signatures_getDefaults()` instead of
  hardcoded literals, keeping a single source of truth.
- **Dark-mode text visibility**: replaced hardcoded `text-muted` classes with
  `text-body-secondary` and added CSS overrides using Bootstrap 5.3 CSS variables
  (`--bs-body-color`, `--bs-secondary-color`) so all helper text adapts correctly
  to both light and dark GLPI themes.
- **Hardcoded UI strings fully extracted to i18n**: all remaining visible strings
  (placeholders, help text, demo data, JS alerts and confirms) are now routed
  through `__()` and included in all locale files (es_MX, en_US, en_GB, fr_FR).

---

## [1.3.1] — 2026-03-13

### Added
- **Touch support in the position editor**: drag-and-drop field handles now respond
  to `touchstart` / `touchmove` / `touchend` events, enabling the editor on tablets
  and touch-screen desktops.
- **QR row in the position table**: the QR code field now appears in the font-size
  table inside the position editor, showing its current X,Y coordinates.
- **Dynamic scale in the position editor** (`applyScale()`): field overlays are
  repositioned in CSS space on every tab-open and window resize event using the ratio
  `img.clientWidth / img.naturalWidth`, so they remain accurately aligned regardless
  of screen width.
- **Spinner on the Save button**: the Save button is disabled and its icon replaced
  with a `spinner-border` on form submit to prevent double-clicks.
- **PNG dimension warning on upload**: if the uploaded template is outside the
  recommended ~650x216 px range, a non-blocking WARNING flash message is shown.
- **`buildMailPayload()` static method** in `PluginSignaturesSignature`: centralizes
  email address resolution, variable substitution, subject/body/footer assembly, and
  filename sanitization. Accepts an `$isTest` flag that prepends `[PRUEBA]` to the
  subject.
- **Incomplete-configuration badge** on the user profile tab: an orange `!` badge
  appears on the tab label when the relevant template or outgoing email is not
  configured.
- **Tab labels and position-editor titles are now translatable**: `General`,
  `Con celular`, `Sin celular`, `Posiciones`, and both editor section headers are
  passed through `__()` and included in all locale files.
- **`filemtime()` cache-buster**: template image URLs append `&t={filemtime}` instead
  of `&t={time()}`, so the browser reloads the image only when the file changes.
- **Independent delete forms**: Delete-template buttons now have individual CSRF
  tokens and are fully decoupled from the main configuration form.
- **`imagecopyresampled()` for QR placement**: replaces `imagecopy()` and uses the
  actual pixel dimensions of the QR image, fixing silent cropping for QRs larger
  than 100 px.
- **Bidirectional font-size auto-adjust for the user name**: the rendering loop now
  both decreases and increases font size (bounded by the configured maximum) so short
  names use the full allocated size.
- **XSS fix in `buildEmailHtml()`**: bold-markdown replacement rewritten with
  `preg_replace_callback()` and `htmlspecialchars()` on the captured group.
- **`ob_end_clean()` on all error paths in `download.php`**: prevents partial binary
  output from being flushed before a redirect header.
- **Signature preview modal**: Preview button on the user profile tab opens the
  rendered PNG in a modal before downloading or sending.
- **Clickable variable badges**: `{nombre}`, `{empresa}`, `{fecha}` insert the
  variable at the cursor position in the last focused email field.
- **Unsaved-positions indicator**: the Positions tab shows an orange dot and a warning
  banner when field positions have been modified but not yet saved.
- **`plugin_signatures_update()`** in `setup.php`: ensures upgrades from older
  versions receive new configuration keys without a full reinstall.
- **`PluginSignaturesConfig` class** (`inc/config.class.php`): centralizes access to
  `glpi_configs`; `invalidate()` is called after saving to guarantee fresh reads.
- **Friendly sender name**: outgoing emails now use `$CFG_GLPI['from_email_name']`
  (fallback `admin_email_name`) as the display name in the From header via
  `\Symfony\Component\Mime\Address`.

### Fixed
- `Content-Disposition` header in `download.php` now includes `filename*=UTF-8''`
  (RFC 6266) for correct handling of non-ASCII file names.
- `catch (Throwable)` blocks in `send.php` and `download.php` now call
  `Toolbox::logError()` so errors appear in the GLPI log.

### Removed
- **`fonts/AvenirBook.ttf`**: never referenced in any code path; removed to reduce
  package size.
- **`front/send_test.php`**: merged into `send.php`. Test-email mode is now triggered
  by posting `is_test=1`.

### Changed
- Button classes updated to native Bootstrap/Tabler tokens for full theme
  compatibility (light and dark): Descargar firma and Enviar por correo use
  `btn-primary`, Vista previa / Enviar correo de prueba / Reset posiciones use
  `btn-outline-secondary`, Guardar uses `btn-warning`, Eliminar plantilla uses
  `btn-danger`.
- All `Config::getConfigurationValues()` calls go through `PluginSignaturesConfig`.
- Locale count updated from 86 to 91 `msgid` entries across `es_MX`, `en_US`,
  `en_GB`, `fr_FR` and the `.pot` template.
- `plugin.xml` updated with the `1.3.1` release entry.

---

## [1.3.0] — 2026-03-08

### Added
- **Signature preview**: new "Preview" button on the user profile tab opens the
  final rendered PNG in a modal before downloading or sending. Uses
  `download.php?preview=1` for inline display.
- **Clickable variable badges**: `{nombre}`, `{empresa}`, `{fecha}` in the email
  configuration panel are now clickable — click inserts the variable at the cursor
  position in the last focused field (subject, body, or footer).
- **Unsaved positions indicator**: the Positions tab shows an orange dot and a warning
  banner when field positions have been modified but not yet saved.
- **`plugin_signatures_update()`** in `setup.php`: ensures installations upgrading
  from older versions receive new configuration keys (position defaults) without
  needing a full reinstall.
- **`PluginSignaturesConfig` class** (`inc/config.class.php`): centralizes all access
  to `glpi_configs` for the plugin. Caches the config array for the duration of the
  HTTP request; `invalidate()` is called after saving to guarantee fresh reads.
- Sending feedback on "Download" and "Send" buttons: spinner replaces the icon and
  the button is disabled on click to prevent double submission.

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
- `plugin_signatures_check_prerequisites()` now validates PHP >= 8.1 and the GD
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
  - Full name (`getFriendlyName()`) with automatic font size reduction (40px -> 20px)
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
