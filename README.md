![Email Signatures](banner.png)

# Email Signatures — GLPI Plugin

GLPI plugin that generates personalized email signature images in PNG format for each user, pulling data directly from GLPI (name, title, email, phone numbers, entity) with optional WhatsApp QR code support. Includes an email delivery system and a visual drag-and-drop position editor.

---

## Features

- Generates a personalized PNG signature per user in one click
- Automatically selects the template based on whether the user has a mobile number
- Auto-adjusts the name font size to prevent overflow
- Optional WhatsApp QR code (only available when the user has a mobile number)
- **Email delivery**: sends the signature directly to the user's registered email address
- **Dynamic email variables**: `{nombre}`, `{empresa}`, `{fecha}` with `**bold**` markdown support
- **Visual position editor**: drag-and-drop fields over the real template at 1:1 scale using the actual TTF fonts, per template
- All field positions and font sizes stored in GLPI config — no PHP editing required
- Configurable from the UI: Facebook page, WhatsApp country code, email subject/body/footer
- Access control: users can only download their own signature; admins can download any user's
- Clean uninstall: removes all configuration values from the database
- Compatible with GLPI 11.0+

---

## Requirements

| Requirement | Minimum version |
|-------------|----------------|
| GLPI | 11.0 |
| PHP | 8.1 |
| PHP GD extension | Any modern version |
| TCPDF | Bundled with GLPI (required only for QR) |

> **Note:** The GD extension is required for image manipulation. The plugin automatically validates its availability before generating any signature.

---

## Installation

### From the marketplace (recommended)

1. In GLPI go to **Setup → Plugins → Browse the marketplace**
2. Search for **Email Signatures** and install it from there

### Manual

1. Download or clone this repository and copy the folder into GLPI's `marketplace` directory:

```
/glpi/marketplace/signatures/
```

2. In GLPI go to **Setup → Plugins**, locate **Email Signatures** and click **Install**, then **Enable**.

### Post-installation

On install, the plugin automatically creates the template storage directory:

```
{GLPI_PLUGIN_DOC_DIR}/signatures/templates/
```

All configuration keys (positions, font sizes, email options) are initialized with default values on first install. Existing values are never overwritten on update.

---

## Configuration

Access from **Setup → Email Signatures**. Requires **config UPDATE** permission.

### General tab

| Field | Description | Example |
|-------|-------------|---------|
| **Facebook page name** | Name only, no URL or @ | `SontechsMX` |
| **WhatsApp country code** | Numeric only, no `+` or spaces | `52` Mexico · `1` USA · `34` Spain |

### Email options

Configure the email sent to users when their signature is delivered.

| Field | Description |
|-------|-------------|
| **Subject** | Email subject line. Supports variables. |
| **Body** | Main email body. Supports variables and `**bold**`. |
| **Footer** | Optional footer below a divider line. |

**Available variables:**

| Variable | Replaced with |
|----------|--------------|
| `{nombre}` | User's full name |
| `{empresa}` | Entity name from GLPI |
| `{fecha}` | Today's date in `dd/mm/yyyy` format |

Use `**text**` for bold text in the body and footer.

**Test email button**: sends a test email to your own registered GLPI address using the current saved configuration. Disabled if GLPI's outgoing mail is not configured or if subject/body are empty.

### Templates (With mobile / Without mobile tabs)

Upload the PNG template to use as background for each case.

**Template restrictions:**
- Format: PNG only
- Max size: 300 KB
- Server-side MIME type validation (not just extension)

From each tab you can preview, download and delete the active template.

### Positions tab

Visual drag-and-drop editor for field positions on each template.

- Template displayed at **natural (1:1) pixel size** — no scaling
- Fields rendered using the **real Avenir TTF fonts** (same as GD)
- Sample text populated with the **current admin's actual GLPI data**
- Drag any field to reposition it; the X/Y coordinates update live
- Per-field font size inputs in the table below the image
- **Restore defaults** button resets all positions for that template
- Positions are saved to `glpi_configs` when you click **Save** — no PHP editing required

---

## Signature generation

1. Go to any user profile in GLPI
2. Select the **Email Signature** tab
3. If the user has a mobile number, you'll see the option to include a WhatsApp QR (checked by default)
4. Click **Download signature** to save the PNG, or **Send signature** to email it directly
5. The file downloads as `signature_FirstnameLastname.png`

> If any template is missing or configuration is incomplete, the buttons are disabled with an informational notice.

---

## Access control

| Action | Required permission |
|--------|---------------------|
| Download own signature | Authenticated user |
| Download another user's signature | `config → UPDATE` (admin) |
| Send own signature by email | Authenticated user |
| Send another user's signature | `config → UPDATE` (admin) |
| View/configure templates | `config → UPDATE` (admin) |
| Edit field positions | `config → UPDATE` (admin) |
| View templates (internal resource) | `config → READ` |

---

## Signature data

| Field in signature | Source in GLPI |
|---|---|
| Full name | `getFriendlyName()` |
| Title / Position | `glpi_usertitles` via `usertitles_id` |
| Email | Primary email in user profile |
| Mobile | `mobile` field in user profile |
| Entity phone | `phonenumber` field of the user's entity |
| Extension / Office | `phone` or `phone2` field in user profile |
| Facebook | Configured in the plugin |
| Website | `website` field of the user's entity |
| WhatsApp QR | Generated from the user's mobile number |

### Phone logic

| User fields | Result in signature |
|---|---|
| `mobile` only | Mobile + entity phone |
| `mobile` + `phone` | Mobile + entity phone + `Ext: xxx` |
| `mobile` + `phone2` | Mobile + entity phone + `Office: xxx` |
| No `mobile` | Entity phone only |
| No `mobile` + `phone` or `phone2` | Entity phone + extension or office |

> `phone2` takes priority over `phone` for the office label.

---

## Plugin structure

```
signatures/
├── fonts/
│   ├── AvenirBlack.ttf         # Font for name and title (bold)
│   ├── AvenirBook.ttf          # Alternative font (reserved)
│   └── AvenirRoman.ttf         # Font for contact data
├── front/
│   ├── config.form.php         # Plugin config page (General, Email, Templates, Positions tabs)
│   ├── download.php            # PNG generation and download endpoint
│   ├── resource.send.php       # Serves template PNGs to the browser
│   ├── send.php                # Sends the signature by email to a user
│   └── send_test.php           # Sends a test email to the current admin
├── inc/
│   ├── paths.class.php         # Centralizes all file and URL paths
│   ├── signature.class.php     # PNG generation logic (reads positions from config)
│   └── user.class.php          # Tab on the user profile
├── locales/
│   ├── signatures.pot          # Translation template
│   ├── es_MX.po / .mo          # Spanish (Mexico)
│   ├── en_US.po / .mo          # English (US)
│   ├── en_GB.po / .mo          # English (GB)
│   └── fr_FR.po / .mo          # French
├── plugin.xml                  # GLPI marketplace descriptor
├── logo.png                    # Plugin icon (transparent background)
├── banner.png                  # Marketplace banner
└── setup.php                   # Install, uninstall, version, defaults
```

---

## Internal generation flow

```
download.php / send.php
  │
  ├── Session & access control check
  ├── checkRequirements() → validates templates, fonts, GD, country code
  ├── checkEmailConfig()  → validates subject and body (send.php only)
  │
  └── generatePNG()
        ├── Reads config (Facebook, WhatsApp code, field positions)
        ├── Determines mobile presence → selects template
        ├── Loads base image with imagecreatefrompng()
        ├── Fetches user and entity data from GLPI
        ├── Auto-adjusts name font size (starts at configured size, min 20px)
        ├── Writes text with imagettftext() using positions from glpi_configs
        ├── If include_qr and mobile → generates QR with TCPDF and composites it
        ├── Saves temporary PNG to GLPI_TMP_DIR
        └── Returns temp file path

  → download.php: sends PNG to browser as download, then deletes temp file
  → send.php: attaches PNG to GLPIMailer, sends email, then deletes temp file
```

---

## Storage

| Type | Location |
|------|----------|
| PNG templates | `{GLPI_PLUGIN_DOC_DIR}/signatures/templates/` |
| TTF fonts | `{plugin_dir}/fonts/` |
| Generated temp PNG | `{GLPI_TMP_DIR}/signature_{userid}.png` (deleted after use) |
| QR temp PNG | `{GLPI_TMP_DIR}/signature_qr_{userid}.png` (deleted after compositing) |
| Configuration & positions | `glpi_configs` table, context `plugin_signatures` |

---

## Uninstall

Uninstalling the plugin from GLPI automatically removes all configuration values (including saved positions) from the database.

> PNG templates stored in `{GLPI_PLUGIN_DOC_DIR}/signatures/templates/` are **not deleted** automatically, so you can reinstall while keeping your templates.

---

## Author

**Edwin Elias Alvarez**  
[https://sontechs.com](https://sontechs.com)

## License

GPLv2+

---
---

![Email Signatures](banner.png)

# Email Signatures — Plugin para GLPI

Plugin para GLPI que genera firmas de correo electrónico personalizadas en formato PNG para cada usuario, con datos obtenidos directamente de GLPI (nombre, cargo, email, teléfonos, entidad) y soporte opcional para código QR de WhatsApp. Incluye sistema de envío por correo y editor visual de posiciones con drag & drop.

---

## Características

- Genera firma PNG personalizada por usuario con un solo clic
- Selecciona automáticamente la plantilla según si el usuario tiene número celular o no
- Ajuste automático del tamaño de fuente del nombre para que nunca se desborde
- Código QR de WhatsApp opcional (solo disponible si el usuario tiene celular)
- **Envío por correo**: entrega la firma directamente al correo registrado del usuario en GLPI
- **Variables dinámicas en el correo**: `{nombre}`, `{empresa}`, `{fecha}` con soporte de negritas `**texto**`
- **Editor visual de posiciones**: drag & drop de campos sobre la plantilla real a escala 1:1 con las fuentes TTF reales, por plantilla
- Todas las posiciones y tamaños de fuente se guardan en la config de GLPI — sin editar PHP
- Configurable desde la interfaz: página de Facebook, código de país para WhatsApp, asunto/cuerpo/pie del correo
- Control de acceso: cada usuario solo puede descargar su propia firma; los administradores pueden descargar la de cualquier usuario
- Desinstalación limpia: elimina toda la configuración de la base de datos al desinstalar
- Compatible con GLPI 11.0+

---

## Requisitos

| Requisito | Versión mínima |
|-----------|---------------|
| GLPI | 11.0 |
| PHP | 8.1 |
| Extensión PHP GD | Cualquier versión moderna |
| TCPDF | Incluido con GLPI (requerido solo para QR) |

> **Nota:** La extensión GD es necesaria para la manipulación de imágenes. El plugin valida automáticamente que esté disponible antes de generar cualquier firma.

---

## Instalación

### Desde el marketplace (recomendado)

1. En GLPI ve a **Configuración → Plugins → Explorar el marketplace**
2. Busca **Email Signatures** e instálalo desde ahí

### Manual

1. Descarga o clona este repositorio y copia la carpeta dentro del directorio `marketplace` de GLPI:

```
/glpi/marketplace/signatures/
```

2. En GLPI ve a **Configuración → Plugins**, localiza **Email Signatures** y haz clic en **Instalar** y luego en **Activar**.

### Post-instalación

Al instalar, el plugin crea automáticamente el directorio donde se almacenan las plantillas:

```
{GLPI_PLUGIN_DOC_DIR}/signatures/templates/
```

Todas las claves de configuración (posiciones, tamaños de fuente, opciones de correo) se inicializan con valores por defecto en la primera instalación. Los valores existentes nunca se sobreescriben en una actualización.

---

## Configuración

Accede desde **Configuración → Email Signatures**. Solo los usuarios con permiso de **actualizar configuración** pueden acceder.

### Pestaña General

| Campo | Descripción | Ejemplo |
|-------|-------------|---------|
| **Nombre de página de Facebook** | Solo el nombre, sin URL ni @ | `SontechsMX` |
| **Código de país para QR de WhatsApp** | Código numérico sin `+` ni espacios | `52` México · `1` EE.UU. · `34` España |

### Opciones del correo electrónico

Configura el correo que se envía al usuario al entregar su firma.

| Campo | Descripción |
|-------|-------------|
| **Asunto** | Línea de asunto. Soporta variables. |
| **Cuerpo** | Cuerpo principal. Soporta variables y `**negrita**`. |
| **Pie** | Pie opcional separado por una línea divisoria. |

**Variables disponibles:**

| Variable | Se reemplaza con |
|----------|-----------------|
| `{nombre}` | Nombre completo del usuario |
| `{empresa}` | Nombre de la entidad en GLPI |
| `{fecha}` | Fecha del día en formato `dd/mm/aaaa` |

Usa `**texto**` para negritas en el cuerpo y pie del correo.

**Botón Enviar correo de prueba**: envía un correo de prueba a tu propia dirección registrada en GLPI con la configuración guardada. Se desactiva si el correo saliente de GLPI no está configurado o si el asunto/cuerpo están vacíos.

### Pestañas Con celular / Sin celular

Sube la plantilla PNG de fondo para cada caso.

**Restricciones:**
- Formato: PNG únicamente
- Tamaño máximo: 300 KB
- Validación de tipo MIME real en el servidor

### Pestaña Posiciones

Editor visual drag & drop para posicionar los campos sobre cada plantilla.

- Plantilla a **tamaño natural (1:1)** — sin escala
- Campos renderizados con las **fuentes Avenir TTF reales**
- Texto de muestra con los **datos reales del administrador actual**
- Arrastra para reposicionar; X/Y se actualiza en tiempo real
- Inputs de tamaño de fuente por campo
- Botón **Restaurar posiciones por defecto** por plantilla
- Se guarda en `glpi_configs` al hacer clic en **Guardar** — sin editar PHP

---

## Generación de firma

1. Ve al perfil de cualquier usuario en GLPI
2. Selecciona la pestaña **Firma de correo**
3. Si el usuario tiene celular, verás la opción de incluir QR de WhatsApp (marcada por defecto)
4. Haz clic en **Descargar firma** o en **Enviar firma** para enviarlo por correo
5. El archivo se descarga como `signature_NombreApellido.png`

---

## Control de acceso

| Acción | Permiso requerido |
|--------|------------------|
| Descargar propia firma | Usuario autenticado |
| Descargar firma de otro usuario | `config → UPDATE` |
| Enviar propia firma por correo | Usuario autenticado |
| Enviar firma de otro usuario | `config → UPDATE` |
| Ver/configurar plantillas | `config → UPDATE` |
| Editar posiciones de campos | `config → UPDATE` |
| Ver plantillas (recurso interno) | `config → READ` |

---

## Datos que incluye la firma

| Campo en la firma | Fuente en GLPI |
|---|---|
| Nombre completo | `getFriendlyName()` |
| Cargo / Título | `glpi_usertitles` vía `usertitles_id` |
| Email | Email principal del perfil |
| Celular | Campo `Móvil` del perfil |
| Teléfono entidad | Campo `Teléfono` de la entidad |
| Extensión / Oficina | Campo `Teléfono` o `Teléfono 2` del perfil |
| Facebook | Configurado en el plugin |
| Sitio web | Campo `Sitio web` de la entidad |
| QR WhatsApp | Generado desde el número móvil |

### Lógica de teléfonos

| Campos del usuario | Resultado |
|---|---|
| Solo `Móvil` | Celular + teléfono entidad |
| `Móvil` + `Teléfono` | Celular + teléfono entidad + `Ext: xxx` |
| `Móvil` + `Teléfono 2` | Celular + teléfono entidad + `Oficina: xxx` |
| Sin `Móvil` | Solo teléfono entidad |
| Sin `Móvil` + `Teléfono` o `Teléfono 2` | Teléfono entidad + extensión u oficina |

> `Teléfono 2` tiene prioridad sobre `Teléfono` para la etiqueta de oficina.

---

## Estructura del plugin

```
signatures/
├── fonts/
│   ├── AvenirBlack.ttf
│   ├── AvenirBook.ttf
│   └── AvenirRoman.ttf
├── front/
│   ├── config.form.php         # Configuración (General, Correo, Plantillas, Posiciones)
│   ├── download.php
│   ├── resource.send.php
│   ├── send.php
│   └── send_test.php
├── inc/
│   ├── paths.class.php
│   ├── signature.class.php     # Lee posiciones desde glpi_configs
│   └── user.class.php
├── locales/
│   ├── signatures.pot
│   ├── es_MX.po / .mo
│   ├── en_US.po / .mo
│   ├── en_GB.po / .mo
│   └── fr_FR.po / .mo
├── plugin.xml
├── logo.png
├── banner.png
└── setup.php                   # Incluye plugin_signatures_getDefaults()
```

---

## Almacenamiento

| Tipo | Ubicación |
|------|-----------|
| Plantillas PNG | `{GLPI_PLUGIN_DOC_DIR}/signatures/templates/` |
| Fuentes TTF | `{directorio_plugin}/fonts/` |
| PNG temporal | `{GLPI_TMP_DIR}/signature_{userid}.png` |
| QR temporal | `{GLPI_TMP_DIR}/signature_qr_{userid}.png` |
| Configuración y posiciones | `glpi_configs`, contexto `plugin_signatures` |

---

## Desinstalación

Al desinstalar se eliminan todos los valores de configuración (incluidas las posiciones). Las plantillas PNG **no se eliminan** automáticamente.

---

## Autor

**Edwin Elias Alvarez**  
[https://sontechs.com](https://sontechs.com)

## Licencia

GPLv2+
