<p align="center"><img src="logo.png" alt="Email Signatures"></p>
<p align="center">
  <img src="logo.png" alt="Email Signatures">
</p>

<h1 align="center">Email Signatures</h1>

<p align="center">
  <strong>GLPI plugin — Generate personalized corporate PNG email signatures for every GLPI user.</strong>
   
Each signature is rendered dynamically over a configurable PNG template using PHP GD
and Avenir TTF fonts, with an optional WhatsApp QR code. Finished signatures can be
downloaded directly or sent to the user's registered email address in one click.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/GLPI-11.0%2B-blue?style=flat-square" alt="GLPI compatibility">
  <img src="https://img.shields.io/badge/License-GPL%20v2%2B-green?style=flat-square" alt="License">
  <img src="https://img.shields.io/badge/PHP-%3E%3D8.2-purple?style=flat-square" alt="PHP">
</p>

---

## Table of Contents

1. [Features](#features)
2. [Requirements](#requirements)
3. [Installation](#installation)
4. [Uninstallation](#uninstallation)
5. [Configuration](#configuration)
   - [General tab](#general-tab)
   - [With mobile tab](#with-mobile-tab-con-celular)
   - [Without mobile tab](#without-mobile-tab-sin-celular)
   - [Positions tab](#positions-tab)
6. [Email variables](#email-variables)
7. [Using the signature — user side](#using-the-signature--user-side)
8. [How signature generation works](#how-signature-generation-works)
9. [Access control and permissions](#access-control-and-permissions)
10. [Localization](#localization)
11. [File structure](#file-structure)
12. [Changelog highlights](#changelog-highlights)
13. [License](#license)

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
| **Multilanguage** | es_MX · en_US · en_GB · fr_FR |

---

## Requirements

| Requirement | Minimum |
|---|---|
| GLPI | 11.0 |
| PHP | 8.1 |
| PHP ext: **GD** | Required — image generation |
| PHP ext: **fileinfo** | Required — MIME validation on template upload |
| PHP ext: **mbstring** | Recommended (used internally by GLPI) |
| TCPDF | Bundled with GLPI — required only for QR code |
| Outgoing mail server | GLPI → Setup → Notifications — required only for email delivery |

> Installation validates PHP ≥ 8.1 and the GD extension with clear error messages.

---

## Installation

### Via ZIP (recommended)

1. Download `signatures-1.3.3.zip` from the [GitHub releases page](https://github.com/monta990/signatures/releases).
2. In GLPI, go to **Setup → Plugins**.
3. Click **Upload a plugin**.
4. Select the ZIP and confirm.
5. Click **Install** next to *Email Signatures*.
6. Click **Enable** to activate.

### Manual

```bash
cd /var/www/glpi/plugins
unzip signatures-1.3.3.zip
# The folder must be named exactly "signatures"
```

Then go to **Setup → Plugins**, install, and enable.

> **Important:** the plugin directory must be named `signatures` (lowercase). Any
> other name (e.g. `signatures-1.3.1`) will prevent GLPI from detecting the plugin.

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

### With mobile tab (Con celular)

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

### Without mobile tab (Sin celular)

Identical to "With mobile" but manages `base2.png`, used for users without a mobile
number. Typically has a slightly different layout without the WhatsApp QR area.

---

### Positions tab

Live drag-and-drop editor for field placement per template.

#### Workflow

1. **Open the tab** — the editor initializes. The background image renders at
   responsive width and all field overlays are scaled to match.
2. **Drag fields** — grab any labeled overlay with the mouse or a finger (touch
   supported). The overlay moves and the hidden GD-coordinate inputs update in real time.
3. **Adjust font size** — use the number input in the table below the editor.
   The overlay font size updates immediately.
4. **Read coordinates** — the *Position* column in the table shows X,Y in GD space
   (natural image pixels) — the exact values that will be stored.
5. **Reset** — click **Restore default positions** to revert all fields for that
   template to factory defaults.
6. **Save** — click **Save** at the bottom right. The button is disabled and shows a
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

## How signature generation works

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
| `es_MX` | Spanish (Mexico) — primary development language |
| `en_US` | English (United States) |
| `en_GB` | English (United Kingdom) |
| `fr_FR` | French (France) |

The `.pot` template contains **121 strings** as of v1.3.3.
All four `.po` source files and compiled `.mo` binaries are included in the package.

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
│   ├── AvenirBlack.ttf          Bold font — name field
│   └── AvenirRoman.ttf          Regular font — all other fields
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
│   ├── signatures.pot           Translation template (91 strings)
│   ├── es_MX.po / es_MX.mo
│   ├── en_US.po / en_US.mo
│   ├── en_GB.po / en_GB.mo
│   └── fr_FR.po / fr_FR.mo
├── templates/                   Created on first upload — not in the ZIP
│   ├── base.png                 Active template for users WITH mobile
│   └── base2.png                Active template for users WITHOUT mobile
├── .gitattributes               Enforces LF line endings
├── CHANGELOG.md
├── LICENSE
├── logo.png                     128×128 plugin icon
├── plugin.xml                   GLPI marketplace manifest
├── README.md                    This file
└── setup.php                    Registration, install/uninstall/update hooks
```

> `templates/` is auto-created on first template upload and is excluded from the
> distribution ZIP. Uninstalling the plugin does not remove this directory.

---

## Changelog highlights

| Version | Date | Summary |
|---|---|---|
| **1.3.3** | 2026-03-14 | Upload simplified: 300 KB hard limit only, no dimension blocking · Recommended template size 650×250 px |
| **1.3.2** | 2026-03-13 | Inline email formatting (`**bold**`, `*italic*`, `__underline__`) · B/I/U toolbar · Position editor improvements · Dark mode fixes |
| **1.3.1** | 2026-03-12 | Touch drag-and-drop · Scale-aware position editor · QR in positions table · `buildMailPayload()` · Incomplete config badge · Independent delete forms · `filemtime()` cache-buster · Spinner on Save · XSS fix in `buildEmailHtml` · `ob_end_clean()` on all error paths · Bidirectional name auto-fit · `imagecopyresampled` for QR · AvenirBook.ttf removed |
| 1.3.0 | 2026-03-08 | Signature preview modal · Clickable variable badges · Unsaved positions dot/banner · `PluginSignaturesConfig` class · `plugin_signatures_update()` |
| 1.2.0 | 2026-03-08 | Visual position editor (drag-and-drop) · `getDefaults()` · Restore defaults · `sanitizeFilename()` · `buildEmailHtml()` |
| 1.1.0 | 2026-03-07 | Email delivery · Email config (subject/body/footer/variables) · Test email · `en_GB` locale |
| 1.0.0 | 2026-02-26 | Initial release |

Full details in [CHANGELOG.md](CHANGELOG.md).

---

## License

GPL-2.0-or-later — see [LICENSE](LICENSE).

---

## Buy me a coffee :)
If you like my work, you can support me by a donate here:

<a href="https://www.buymeacoffee.com/monta990" target="_blank"><img src="https://cdn.buymeacoffee.com/buttons/default-yellow.png" alt="Buy Me A Coffee" height="51px" width="210px"></a>

---

*Generated and maintained by [Sontechs](https://sontechs.com).*

---
<p align="center"><img src="logo.png" alt="Email Signatures"></p>
# Email Signatures — Plugin GLPI (Español)

> Compatible con GLPI 11.0+ · PHP 8.1+ · GPL-2.0-or-later  
> Autor: [Edwin Elias Alvarez](https://sontechs.com) ([@monta990](https://github.com/monta990))

Genera firmas de correo corporativas en PNG personalizadas para cada usuario de GLPI.
La firma se renderiza dinámicamente sobre una plantilla PNG configurable usando PHP GD
y fuentes TTF Avenir, con código QR de WhatsApp opcional. Las firmas terminadas se
pueden descargar directamente o enviar al correo del usuario con un solo clic.

---

## Tabla de contenido

1. [Características](#características)
2. [Requisitos](#requisitos)
3. [Instalación](#instalación)
4. [Desinstalación](#desinstalación)
5. [Configuración](#configuración-1)
6. [Variables de correo](#variables-de-correo-1)
7. [Uso de la firma](#uso-de-la-firma)
8. [Cómo funciona la generación](#cómo-funciona-la-generación)
9. [Control de acceso](#control-de-acceso)
10. [Localización](#localización)

---

## Características

| Función | Detalle |
|---|---|
| **Dos plantillas PNG** | Una para usuarios con celular, otra sin. Selección automática por campo `mobile`. |
| **Texto dinámico** | Nombre, título, correo, teléfonos, Facebook, web — con fuentes Avenir via PHP GD. |
| **Auto-ajuste del nombre** | Tamaño de fuente ajustado arriba/abajo dentro del rango configurado. |
| **QR de WhatsApp** | Con TCPDF (incluido en GLPI). Compuesto con `imagecopyresampled()` a tamaño real. |
| **Descarga** | PNG en tiempo real, sin almacenamiento permanente. |
| **Envío por correo** | PNG adjunto vía `GLPIMailer`. |
| **Correo de prueba** | Al administrador, con prefijo `[PRUEBA]` y banner de aviso. |
| **Vista previa** | Modal con el PNG final antes de descargar o enviar. |
| **Editor visual** | Drag & drop con mouse y toque. Escala dinámica según ancho de pantalla. |
| **Tamaño de fuente por campo** | Input numérico en la tabla del editor, actualización en vivo. |
| **Badges de variables** | Inserción al cursor con un clic. |
| **Indicador de cambios** | Punto naranja + banner cuando hay posiciones sin guardar. |
| **Validación de upload** | Solo PNG hasta 300 KB (límite duro). Dimensiones recomendadas: 650×250 px, mostradas como hint debajo del input. |
| **Badge de config incompleta** | `!` naranja en la pestaña del perfil si falta plantilla o config de correo. |
| **Formularios de eliminación independientes** | Botones "Eliminar" desacoplados del form principal, con CSRF propio. |
| **Multiidioma** | es_MX · en_US · en_GB · fr_FR |

---

## Requisitos

| Requisito | Mínimo |
|---|---|
| GLPI | 11.0 |
| PHP | 8.1 |
| Ext PHP: **GD** | Requerida |
| Ext PHP: **fileinfo** | Requerida |
| TCPDF | Incluido en GLPI |
| Servidor de correo saliente | Solo para envío de correo |

---

## Instalación

### Vía ZIP

1. Descarga `signatures-1.3.3.zip` desde [GitHub releases](https://github.com/monta990/signatures/releases).
2. En GLPI: **Configuración → Complementos → Subir un complemento**.
3. Selecciona el ZIP.
4. Haz clic en **Instalar** y luego en **Habilitar**.

### Manual

```bash
cd /var/www/glpi/plugins
unzip signatures-1.3.3.zip
# El directorio debe llamarse exactamente "signatures"
```

> El directorio debe llamarse `signatures` en minúsculas.

---

## Desinstalación

**Configuración → Complementos → Deshabilitar → Desinstalar.**

Elimina las claves de `glpi_configs`. Los PNG de plantilla en disco NO se eliminan.

---

## Configuración

**Configuración → Complementos → Email Signatures → Configurar.**

### Pestaña General

| Campo | Descripción |
|---|---|
| **Página de Facebook** | Handle de la página corporativa. Vacío = omitir campo en la firma. |
| **Código de país WhatsApp** | Número sin `+` (ej. `52` para México). Vacío = sin QR. |
| **Asunto del correo** | Texto plano. Soporta `{nombre}`, `{empresa}`, `{fecha}`. |
| **Cuerpo del correo** | HTML. Soporta variables y `**negrita**`. |
| **Pie del correo** | Párrafo final opcional. Mismo soporte de variables. |

Haz clic en cualquier badge de variable para insertarla en el cursor del último campo
enfocado. El botón **Enviar correo de prueba** envía la configuración guardada con
prefijo `[PRUEBA]` al correo del administrador.

### Pestaña Con celular

Gestiona `base.png` (plantilla para usuarios con número celular).

- Vista previa de la plantilla actual. Clic → descarga.
- Subir nueva: PNG, máx. 300 KB (límite duro). Validación MIME con `finfo`. Dimensiones recomendadas: 650×250 px.
- Eliminar: formulario independiente con CSRF propio.

**Recomendación de diseño:** 650×250 px, 96 dpi. Deja áreas en blanco para los campos.

### Pestaña Sin celular

Idéntica a "Con celular", gestiona `base2.png` (para usuarios sin celular).

### Pestaña Posiciones

Editor drag & drop visual.

1. **Abre la pestaña** → los overlays se escalan automáticamente al tamaño visible.
2. **Arrastra campos** con mouse o toque.
3. **Ajusta tamaño de fuente** en la tabla (input numérico por campo).
4. **Lee coordenadas** en la columna Posición (espacio GD en px).
5. **Reset** → Restaurar posiciones por defecto.
6. **Guarda** → botón Guardar con spinner anti-doble clic.

Los cambios sin guardar muestran punto naranja en la pestaña y banner de aviso.

---

## Variables de correo

| Variable | Fuente |
|---|---|
| `{nombre}` | `User::getFriendlyName()` |
| `{empresa}` | `Entity::name` de la entidad del usuario |
| `{fecha}` | `date('d/m/Y')` al enviar |

---

## Uso de la firma

Cada perfil de usuario muestra la pestaña **Firma de correo**.
Badge naranja `!` = plantilla o configuración de correo incompleta.

- **Descargar firma** → PNG generado en tiempo real. Checkbox para incluir/omitir QR.
- **Enviar por correo** → PNG adjunto al correo registrado del usuario.
- **Vista previa** → modal con el PNG final.

**Instalar en el cliente de correo:**

| Cliente | Pasos |
|---|---|
| **Outlook (escritorio)** | Archivo → Opciones → Correo → Firmas → Nueva → Insertar → Imagen. |
| **Outlook Web** | Configuración → Ver toda la config → Correo → Redactar y responder → icono de imagen. |
| **Gmail** | Configuración → Ver toda la config → General → Firma → Nueva → icono de imagen. |
| **Apple Mail** | Mail → Preferencias → Firmas. Arrastra el PNG. |
| **Thunderbird** | Config. de cuenta → Texto de firma → Adjuntar desde archivo. |

---

## Cómo funciona la generación

### Selección de plantilla

```
¿Usuario tiene celular?  →  SÍ: base.png  /  NO: base2.png
```

### Campos renderizados

Nombre (Avenir Black, auto-ajuste 40 px) → Título → Correo → Celular →
Teléfono entidad → Extensión → Facebook → Web → QR WhatsApp.

Coordenadas y tamaños desde `glpi_configs`, con fallback a defaults integrados.

### Auto-ajuste del nombre

Ciclo: mide ancho con `imagettfbbox()` → si desborda, baja 1 px → si hay espacio, sube 1 px → repite hasta que el texto llene el ancho disponible sin overflow.

### QR de WhatsApp

URL `https://wa.me/{pais}{celular}` → `TCPDF2DBarcode` genera PNG temporal →
`imagecopyresampled()` con dimensiones reales del QR → archivo temporal eliminado.

---

## Control de acceso

| Acción | Quién |
|---|---|
| Descargar/enviar propia firma | Cualquier usuario autenticado |
| Descargar/enviar firma de otro | `config UPDATE` |
| Configurar el plugin | `config UPDATE` |
| Subir / eliminar plantillas | `config UPDATE` |
| Correo de prueba | `config UPDATE` |

---

## Localización

| Código | Idioma |
|---|---|
| `es_MX` | Español (México) — idioma primario |
| `en_US` | English (United States) |
| `en_GB` | English (United Kingdom) |
| `fr_FR` | Français (France) |

91 strings en v1.3.1. Para agregar idioma: copia `.pot` → traduce `msgstr` → compila con `msgfmt` o Poedit.

---

## Comprame un cafe :)
Si te gusta mi trabajo, me puedes apoyar con una donación:

<a href="https://www.buymeacoffee.com/monta990" target="_blank"><img src="https://cdn.buymeacoffee.com/buttons/default-yellow.png" alt="Buy Me A Coffee" height="51px" width="210px"></a>

---

*Generado y mantenido por [Sontechs](https://sontechs.com).*
