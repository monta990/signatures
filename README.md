<p align="center">
  <img src="logo.png" alt="Email Signatures">
</p>

<h1 align="center">Email Signatures</h1>

<p align="center">
  <strong>GLPI plugin — Generate personalized corporate PNG email signatures for every GLPI user</strong>
</p>
   
<p align="center">
  <a href="https://github.com/glpi-project/glpi" target="_blank"><img src="https://img.shields.io/badge/GLPI-11.0%2B-blue" alt="GLPI compatibility"></a>
  <a href="https://www.gnu.org/licenses/gpl-3.0.html" target="_blank"><img src="https://img.shields.io/badge/License-GPL%20v3%2B-green" alt="License"></a>
  <a href="https://php.net/" target="_blank"><img src="https://img.shields.io/badge/PHP-%3E%3D8.2-purple" alt="PHP"></a>
  <a href="https://github.com/monta990/signatures/releases" target="_blank"><img alt="GitHub Downloads (all assets, all releases)" src="https://img.shields.io/github/downloads/monta990/signatures/total"></a>
</p>

---

## Overview

Each signature is rendered dynamically over a configurable PNG template using PHP GD and Avenir TTF or custom fonts, with an optional WhatsApp QR code. Finished signatures can be downloaded directly or sent to the user's registered email address in one click.

---

## Features

| Feature | Details |
|---|---|
| **Two PNG templates** | One for users with a mobile number, one without. Template selection is automatic based on the user's `mobile` field. |
| **Dynamic text overlay** | Name, title, email, phone, extension, Facebook page, corporate website — rendered with PHP GD using Avenir Black/Roman TTF fonts. |
| **Name auto-fit** | Font size is adjusted up and down (within the configured range) so short names use the full allocated width and long names never overflow. |
| **WhatsApp QR code** | Generated with TCPDF (bundled with GLPI) and composited with `imagecopyresampled()` using the QR's actual pixel dimensions to avoid cropping. |
| **Download** | PNG generated on the fly and streamed as a browser download — no permanent file stored. |
| **Email delivery** | Sends the PNG as an attachment using GLPI's outgoing mail system (`GLPIMailer`). |
| **Admin test email** | Sends a sample to the administrator's own address with `[PRUEBA]` prefix and warning banner. |
| **Signature preview** | Opens the final PNG in a modal before downloading or sending. |
| **Inline email formatting** | Body and footer fields support `**bold**`, `*italic*` and `__underline__` markers — rendered as inline CSS styles compatible with Outlook and Gmail. A B / I / U toolbar wraps or unwraps selected text in one click. |
| **Visual position editor** | Drag-and-drop fields over the live template image. Mouse and touch (tablet) support. |
| **Scale-aware editor** | Field coordinates are stored in GD pixel space and translated to CSS on every tab-open and window resize, keeping overlays aligned at any screen width. |
| **Font size per field** | Number input per field in the editor table; overlay updates live. |
| **Clickable variable badges** | `{nombre}`, `{empresa}`, `{fecha}` insert at the cursor in the last focused email field. |
| **Unsaved changes indicator** | Orange dot on the Positions tab + warning banner when positions are changed but not yet saved. |
| **Upload validation** | Only PNG files up to 300 KB are accepted (hard limit). Recommended dimensions are 650×250 px, shown as a hint below the file input. |
| **Incomplete config badge** | Orange `!` badge on the user profile tab when the relevant template is missing or email is not configured. |
| **Independent delete forms** | Delete-template buttons use their own `<form>` with dedicated CSRF tokens, decoupled from the main config form. |
| **CSRF protection** | All POST endpoints use GLPI's built-in CSRF token validation. |
| **Custom fonts** | Upload TTF or OTF font files from the Fonts tab. The plugin reads each file's internal `name` table to display its real name. Built-in Avenir Black and Avenir Roman are always available. |
| **Per-role font selection** | Choose **Name font** (used for the signature name) and **Body font** (used for all other fields) independently. Both built-in and uploaded fonts are available for either role. |
| **Per-field visibility toggle** | Checkbox next to each field in the position editor enables or disables that field independently per template. Hidden fields are skipped during PNG generation — no blank space left. |
| **Multilanguage** | es_MX · en_US · en_GB · fr_FR |

---

## Requirements

| Requirement | Minimum |
|---|---|
| GLPI | ≥ 11.0.0 |
| PHP | ≥ 8.2 |
| PHP ext: **GD** | Required — image generation |
| PHP ext: **fileinfo** | Required — MIME validation on template upload |
| PHP ext: **mbstring** | Recommended (used internally by GLPI) |
| TCPDF | Bundled with GLPI — required only for QR code |
| Outgoing mail server | GLPI → Setup → Notifications — required only for email delivery |

---

## Installation

### Via ZIP (recommended)

1. Download the latest `.zip` from the [GitHub releases page](https://github.com/monta990/signatures/releases).
2. In GLPI, go to **Setup → Plugins**.
3. Click **Upload a plugin**.
4. Select the ZIP and confirm.
5. Click **Install** next to *Email Signatures*.
6. Click **Enable** to activate.

### Manual

```bash
cd /var/www/glpi/plugins
unzip signatures-X.X.X.zip
# The folder must be named exactly "signatures"
```

Then go to **Setup → Plugins**, install, and enable.

> **Important:** the plugin directory must be named `signatures` (lowercase).

---

## Uninstallation

1. Go to **Setup → Plugins**.
2. Click **Disable** next to *Email Signatures*, then **Uninstall**.

Uninstalling removes all `plugin_signatures` keys from `glpi_configs`.
**PNG template files on disk are NOT deleted** — they remain in
`plugins/signatures/templates/` and will be reused if the plugin is reinstalled.

---

## Configuration

Go to **Setup → Plugins → Email Signatures → Configure**.
The page has four tabs.

---

### General tab

#### Facebook page

Enter the Facebook page name or handle (e.g. `MiEmpresa`) to show in the signature.
Leave empty to omit the Facebook field entirely.

#### WhatsApp country code

Numeric country code **without** `+` (e.g. `52` for Mexico, `1` for USA/Canada,
`34` for Spain). Used to build `https://wa.me/{code}{mobile}` encoded in the QR.

> If this field is empty, no QR code will be generated regardless of whether the
> user has a mobile number.

#### Email options

These fields define the email sent when a user clicks **Send by email**.

| Field | Notes |
|---|---|
| **Email subject** | Plain text. Supports `{nombre}`, `{empresa}`, `{fecha}`. |
| **Email body** | HTML. Supports variables and `**bold**` markdown. |
| **Email footer** | Optional footer paragraph. Same variable and markdown support. |

Click any variable badge to insert it at the cursor in the last focused field.

**Bold markdown:** wrap text in `**double asterisks**` to render it as
`<span style="font-weight:bold">` — compatible with Outlook, Gmail, and most
webmail clients.

#### Test email button

Sends a copy of the **currently saved** configuration to the administrator's own
registered email address. The subject is prefixed with `[PRUEBA]` and the body
includes a yellow warning banner.

> Save the form first if you want to test new wording — the button uses saved values,
> not the current unsaved content of the form fields.

---

### With mobile tab

Manages `base.png` — the template used for users who have a mobile number set in
their GLPI profile.

| Element | Description |
|---|---|
| **Current template** | Shows the active `base.png` at full responsive width. Click the image to download it. |
| **Upload new** | PNG only, max 300 KB (hard limit enforced server-side). MIME validated with `finfo`. A browser preview is shown before saving. Recommended dimensions: 650×250 px (shown as a hint). |
| **Delete** | Removes `base.png` from disk. Uses a dedicated mini-form with its own CSRF token so the main configuration is not affected. |

**Template design tips:**

- Recommended size: **650 × 250 px** at 96 dpi.
- Leave blank or light-colored areas where text fields will appear (name, title, etc.).
- PNG with transparency is supported.
- Avoid placing important graphic elements in the text regions; they will be covered.
- Design the "Without mobile" template to omit the mobile and QR column.

---

### Without mobile tab

Identical to "With mobile" but manages `base2.png`, used for users without a mobile
number. Typically has a slightly different layout without the WhatsApp QR area.

---

### Positions tab

Live drag-and-drop editor for field placement per template.

#### Workflow

1. **Open the tab** — the editor initializes. The background image renders at
   responsive width and all field overlays are scaled to match.
2. **Toggle fields** — check or uncheck the checkbox at the start of each row to
   show or hide that field. Hidden fields disappear from the canvas and are skipped
   during PNG generation.
3. **Drag fields** — grab any labeled overlay with the mouse or a finger (touch
   supported). The overlay moves and the hidden GD-coordinate inputs update in real time.
4. **Adjust font size** — use the number input in the table below the editor.
   The overlay font size updates immediately.
5. **Read coordinates** — the *Position* column in the table shows X,Y in GD space
   (natural image pixels) — the exact values that will be stored.
6. **Reset** — click **Restore default positions** to revert all fields for that
   template to factory defaults.
7. **Save** — click **Save** at the bottom right. The button is disabled and shows a
   spinner while submitting to prevent double clicks.

An orange dot appears on the tab label and a warning banner shows at the bottom of
the page whenever positions have been changed but not yet saved.

#### Coordinate system

All coordinates are stored and displayed in **GD pixel space** (the natural pixel
size of the PNG template, typically 650×250 px). When the tab opens or the window
is resized, `applyScale()` multiplies the GD coordinates by
`img.clientWidth / img.naturalWidth` to get CSS pixel positions for the overlays.
When a field is dragged, `syncInputs()` divides the CSS position back by the same
ratio before updating the hidden inputs. This two-way conversion keeps the visual
editor and the stored values consistent at any display size.

#### Fields — With mobile template

| Key | Rendered content | Font |
|---|---|---|
| `nombre` | User full name (`getFriendlyName()`) | Avenir Black |
| `titulo` | User title / position (from `glpi_usertitles`) | Avenir Roman |
| `email` | Primary email (`glpi_useremails`, `is_default=1`) | Avenir Roman |
| `mobile` | User's mobile number | Avenir Roman |
| `tel` | Entity phone number | Avenir Roman |
| `ext` | `phone2` if set, otherwise `phone` | Avenir Roman |
| `facebook` | Facebook page name (from plugin config) | Avenir Roman |
| `web` | Corporate website (from entity) | Avenir Roman |
| `qr` | WhatsApp QR code image (composited, not text) | — |

#### Fields — Without mobile template

| Key | Rendered content | Font |
|---|---|---|
| `nombre` | User full name | Avenir Black |
| `titulo` | User title / position | Avenir Roman |
| `email` | Primary email address | Avenir Roman |
| `tel` | Entity phone number | Avenir Roman |
| `ext` | Extension or office phone | Avenir Roman |
| `facebook` | Facebook page name | Avenir Roman |
| `web` | Corporate website | Avenir Roman |

---

### Fonts tab

Upload and manage custom fonts for signature rendering.

| Element | Description |
|---|---|
| **Upload font** | TTF or OTF files, max 2 MB. The plugin reads the file's internal `name` table and shows the real font name everywhere (selects, table). |
| **Name font** | Font used to render the user's name in the signature. Defaults to Avenir Black. |
| **Body font** | Font used for all other fields (title, email, phone, etc.). Defaults to Avenir Roman. |
| **Installed fonts** | Table of uploaded fonts showing real name, filename, current role badge, and a delete button. |

> Avenir Black and Avenir Roman are always available as built-in options and cannot be deleted.
> Both fonts are available for either role — you can assign Avenir Roman as the name font or Avenir Black as the body font if needed.

---

## Email variables

| Variable | Resolved from |
|---|---|
| `{nombre}` | `User::getFriendlyName()` (first + last name) |
| `{empresa}` | `Entity::name` for the user's entity. Falls back to the root entity name or `$CFG_GLPI['name']` for root-entity users. |
| `{fecha}` | `date('d/m/Y')` at the moment the email is sent |

---

## Using the signature — user side

Every GLPI user profile shows an **Email Signature** tab registered by
`PluginSignaturesUser`. The tab is visible to the owner and to administrators.

> An orange `!` badge on the tab means the relevant template has not been uploaded
> yet, or the outgoing email configuration is incomplete. Buttons are shown but
> generation will fail with a descriptive error message.

### Download signature

Click **Download signature** to generate the PNG and save it to your computer.

- The PNG is generated on the fly — no file is stored permanently on the server.
- File name format: `signature_{name}_{userid}.png`.
- **Include QR** checkbox: shown only if the user has a mobile number. Controls
  whether the WhatsApp QR is included in this specific download.

**How to install the signature in your email client:**

| Client | Steps |
|---|---|
| **Outlook (desktop)** | File → Options → Mail → Signatures → New. In the editor: Insert → Picture → select the PNG. |
| **Outlook (web / OWA)** | Settings → View all → Mail → Compose and reply → Signature → image icon → upload the PNG. |
| **Gmail** | Settings (gear) → See all settings → General → Signature → New → image icon → upload. |
| **Apple Mail** | Mail → Preferences → Signatures. Drag the PNG into the signature editor. |
| **Thunderbird** | Account Settings → Signature text → Attach signature from file. Select the saved PNG. |

### Send by email

Click **Send by email** to deliver the PNG directly to the email address registered
in the user's GLPI profile (`glpi_useremails`, `is_default = 1`).

- The signature is generated, attached, sent, and the temporary file is deleted
  immediately — nothing is stored on the server.
- Subject, body, and footer come from the plugin configuration with variables
  substituted at send time.
- A success flash message shows the destination address.
- Errors (no email, mail server not configured, generation failure) show descriptive
  flash messages and are logged via `Toolbox::logError()`.

### Preview

Click **Preview** to open a modal showing the final rendered PNG without downloading
it. Useful for verifying the layout before sending.

---

### Template selection

```
Does the user have a mobile number?
  ├─ YES → base.png   (With mobile template)
  └─ NO  → base2.png  (Without mobile template)
```

If the selected template file does not exist, generation stops and the user sees a
descriptive error message pointing to the configuration page.

### Fields rendered

All coordinates and font sizes come from `glpi_configs` (set in the Positions tab),
with per-key fallback to built-in defaults if not yet configured.

| Field | Source | Default size |
|---|---|---|
| Name | `User::getFriendlyName()` | 40 px (auto-fit) |
| Title | `glpi_usertitles` via `usertitles_id` | 11 px |
| Email | `glpi_useremails` (`is_default=1`) | 11 px |
| Mobile | `User::fields['mobile']` | 11 px |
| Entity phone | `Entity::phonenumber` | 11 px |
| Extension | `User::fields['phone2']` if set, else `phone` | 11 px |
| Facebook | `plugin_signatures.facebook_page` config | 11 px |
| Website | `Entity::website` | 11 px |
| QR code | TCPDF2DBarcode → imagecopyresampled | 100×100 px |

### Font resolution

The active font for each role is resolved at generation time:

1. If a custom font is configured and its file exists in `GLPI_PLUGIN_DOC_DIR/signatures/fonts/` → use it.
2. If a built-in font is explicitly selected (`AvenirBlack.ttf` or `AvenirRoman.ttf`) → use the bundled file.
3. Otherwise → fall back to the default built-in (Avenir Black for name, Avenir Roman for body).

### Font and name auto-fit

1. Start at the configured maximum size (default 40 px).
2. Measure text width with `imagettfbbox()`.
3. If the text exceeds available width → decrease by 1 px, repeat.
4. If the text fits and there is room → increase by 1 px (up to the maximum), repeat.

This guarantees short names like "Ana" render at full size while long names like
"María Fernanda Rodríguez Bustamante" are reduced without overflow.

### WhatsApp QR code

1. Build `https://wa.me/{countryCode}{mobile}` (non-numeric characters stripped from `mobile`).
2. `TCPDF2DBarcode` generates a QR image into a temp file.
3. Load the QR with `imagecreatefrompng()`.
4. Composite onto the signature with `imagecopyresampled()` using `imagesx()` /
   `imagesy()` for actual dimensions — not a hardcoded 100 px, preventing cropping.
5. Temp file deleted immediately after compositing.

---

## Access control and permissions

| Action | Who |
|---|---|
| Download own signature | Any authenticated user |
| Download another user's signature | `config UPDATE` right |
| Send own signature by email | Any authenticated user |
| Send another user's signature by email | `config UPDATE` right |
| Access plugin configuration page | `config UPDATE` right |
| Upload / delete templates | `config UPDATE` right |
| Send test email | `config UPDATE` right |

Access checks use `Session::haveRight('config', UPDATE)` and
`Session::checkRight()`. Unauthorized requests redirect silently to the GLPI root.

---

## Localization

| Code | Language |
|---|---|
| `es_MX` | Spanish (Mexico) |
| `en_US` | English (United States) |
| `en_GB` | English (United Kingdom) |
| `fr_FR` | French (France) |

To add a new language:

```bash
# 1. Copy the template
cp locales/signatures.pot locales/de_DE.po

# 2. Edit the .po file (fill in msgstr values)
# Use Poedit, Virtaal, or any PO editor

# 3. Compile
msgfmt locales/de_DE.po -o locales/de_DE.mo
```

---

## File structure

```
signatures/
├── fonts/
│   ├── AvenirBlack.ttf          Built-in name font (Avenir Black)
│   └── AvenirRoman.ttf          Built-in body font (Avenir Roman)
├── front/
│   ├── config.form.php          Plugin configuration UI (4-tab page)
│   ├── download.php             Generates and streams the PNG download
│   ├── resource.send.php        Serves template PNGs to the browser
│   └── send.php                 Sends signature by email (normal + test mode via is_test=1)
├── inc/
│   ├── config.class.php         glpi_configs read/write wrapper (request-cached)
│   ├── paths.class.php          Centralizes all file paths and public URLs
│   ├── signature.class.php      Image generation, email assembly, QR composition
│   └── user.class.php           GLPI tab registration, user-facing UI
├── locales/
│   ├── signatures.pot           Translation template
│   ├── es_MX.po / es_MX.mo
│   ├── en_US.po / en_US.mo
│   ├── en_GB.po / en_GB.mo
│   └── fr_FR.po / fr_FR.mo
├── templates/                   Created on first upload — not in the ZIP
│   ├── base.png                 Active template for users WITH mobile
│   └── base2.png                Active template for users WITHOUT mobile
├── .gitignore
├── CHANGELOG.md
├── LICENSE
├── logo.png                     Plugin icon
├── plugin.xml                   GLPI marketplace manifest
├── README.md                    This file
└── setup.php                    Registration, install/uninstall/update hooks
```

> `templates/` is auto-created on first template upload and is excluded from the
> distribution ZIP. Uninstalling the plugin does not remove this directory.

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

---

## Author

Edwin Elias Alvarez — [GitHub](https://github.com/monta990).

---

## Buy me a coffee :)

If you like my work, you can support me with a donation:

<a href="https://www.buymeacoffee.com/monta990" target="_blank"><img src="https://cdn.buymeacoffee.com/buttons/default-yellow.png" alt="Buy Me A Coffee" height="51px" width="210px"></a>

---

## License

GPL-3.0-or-later — see [LICENSE](LICENSE).

## Issues

Report bugs or request features on the [issue tracker](https://github.com/monta990/signatures/issues).

---