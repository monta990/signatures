# Email Signatures — Plugin para GLPI

Plugin para GLPI que genera firmas de correo electrónico personalizadas en formato PNG para cada usuario, con datos obtenidos directamente de GLPI (nombre, cargo, email, teléfonos, entidad) y soporte opcional para código QR de WhatsApp.

---

## Características

- Genera firma PNG personalizada por usuario con un solo clic
- Selecciona automáticamente la plantilla según si el usuario tiene número celular o no
- Ajuste automático del tamaño de fuente del nombre para que nunca se desborde
- Código QR de WhatsApp opcional (solo disponible si el usuario tiene celular)
- Configurable desde la interfaz: página de Facebook y código de país para WhatsApp
- Control de acceso: cada usuario solo puede descargar su propia firma; los administradores pueden descargar la de cualquier usuario
- Desinstalación limpia: elimina la configuración de la base de datos al desinstalar
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

---

## Configuración

Accede desde **Configuración → Email Signatures**. Solo los usuarios con permiso de **actualizar configuración** pueden acceder a esta página.

### Pestaña General

| Campo | Descripción | Ejemplo |
|-------|-------------|---------|
| **Nombre de página de Facebook** | Solo el nombre, sin URL ni @ | `SontechsMX` |
| **Código de país para QR de WhatsApp** | Código numérico sin `+` ni espacios | `52` México · `1` EE.UU. · `34` España |

> El campo de código de país solo acepta dígitos. Si no está configurado, el plugin bloqueará la generación de firmas con QR y mostrará un mensaje de error indicando que debe configurarse.

### Pestaña Con celular

Sube la plantilla PNG que se usará como fondo cuando el usuario **tenga número celular** registrado. Esta plantilla normalmente incluye espacio visual para el QR de WhatsApp y los campos de teléfonos.

### Pestaña Sin celular

Sube la plantilla PNG que se usará cuando el usuario **no tenga número celular**. Normalmente es una versión simplificada sin el área del QR.

**Restricciones de las plantillas:**
- Formato: PNG únicamente
- Tamaño máximo: 300 KB
- El plugin valida el tipo MIME real del archivo en el servidor (no solo la extensión)

Desde cada pestaña puedes ver la plantilla activa, descargarla y eliminarla.

---

## Generación de firma

1. Ve al perfil de cualquier usuario en GLPI
2. Selecciona la pestaña **Firma de correo**
3. Si el usuario tiene número móvil, verás la opción de incluir QR de WhatsApp (marcada por defecto). Si no tiene celular, se muestra un aviso informativo
4. Haz clic en **Descargar firma**
5. El archivo se descarga como `signature_NombreUsuario.png`

> Si alguna plantilla no está cargada o la configuración está incompleta, el botón de descarga aparece deshabilitado y se muestra un aviso.

---

## Control de acceso

| Acción | Permiso requerido |
|--------|------------------|
| Descargar propia firma | Usuario autenticado |
| Descargar firma de otro usuario | `config → UPDATE` (administrador) |
| Ver/configurar plantillas | `config → UPDATE` (administrador) |
| Ver plantillas (recurso interno) | `config → READ` |

---

## Datos que incluye la firma

| Campo en la firma | Fuente en GLPI |
|---|---|
| Nombre completo | `getFriendlyName()` del usuario |
| Cargo / Título | Tabla `glpi_usertitles` vía `usertitles_id` del perfil |
| Email | Email marcado como principal en el perfil |
| Celular | Campo `Móvil` del perfil |
| Teléfono entidad | Campo `Teléfono` de la entidad del usuario |
| Extensión / Oficina | Campo `Teléfono` o `Teléfono 2` del perfil |
| Facebook | Configurado en el plugin |
| Sitio web | Campo `Sitio web` de la entidad del usuario |
| QR WhatsApp | Generado desde el número móvil del usuario |

### Lógica de teléfonos

El plugin determina automáticamente qué mostrar según los campos disponibles:

| Campos del usuario | Resultado en la firma |
|---|---|
| Solo `Móvil` | Celular + teléfono entidad |
| `Móvil` + `Teléfono` | Celular + teléfono entidad + `Ext: xxx` |
| `Móvil` + `Teléfono 2` | Celular + teléfono entidad + `Oficina: xxx` |
| Sin `Móvil` | Solo teléfono entidad |
| Sin `Móvil` + `Teléfono` o `Teléfono 2` | Teléfono entidad + extensión u oficina |

> `Teléfono 2` tiene prioridad sobre `Teléfono` para la etiqueta de oficina.

---

## Posicionamiento del texto — importante

> ⚠️ Las coordenadas X/Y, tamaños de fuente y posición del QR sobre la imagen están **definidos manualmente (hardcoded)** en `inc/signature.class.php`. Esto significa que el posicionamiento está calibrado para un tamaño específico de plantilla PNG.

Si cambias las dimensiones de tus plantillas, deberás ajustar manualmente los valores en `signature.class.php`. Los parámetros relevantes son:

```php
// Nombre — tamaño automático desde 40px hasta mínimo 20px
imagettftext($img, $size, 0, 20,  75,  ...);  // X=20,  Y=75

// Cargo / Título
imagettftext($img, 11,    0, 20,  104, ...);  // X=20,  Y=104

// Email
imagettftext($img, 11,    0, 63,  138, ...);  // X=63,  Y=138

// Teléfonos (plantilla con celular)
imagettftext($img, 11,    0, 63,  161, ...);  // Celular      X=63
imagettftext($img, 11,    0, 185, 161, ...);  // Tel entidad  X=185
imagettftext($img, 11,    0, 283, 161, ...);  // Ext/Oficina  X=283

// Teléfonos (plantilla sin celular)
imagettftext($img, 11,    0, 63,  161, ...);  // Tel entidad  X=63
imagettftext($img, 11,    0, 160, 161, ...);  // Ext/Oficina  X=160

// Facebook y web
imagettftext($img, 11,    0, 63,  183, ...);  // Facebook     X=63
imagettftext($img, 11,    0, 185, 183, ...);  // Web          X=185

// QR WhatsApp — posición y tamaño (100x100 px)
imagecopy($img, $qr, 560, 130, 0, 0, 100, 100); // X=560, Y=130
```

El nombre es el único campo con tamaño dinámico: empieza en 40px y reduce de 1 en 1 hasta 20px para que no se desborde del ancho disponible de la plantilla.

---

## Estructura del plugin

```
signatures/
├── fonts/
│   ├── AvenirBlack.ttf         # Fuente para nombre y cargo (negrita)
│   ├── AvenirBook.ttf          # Fuente alternativa (reservada)
│   └── AvenirRoman.ttf         # Fuente para datos de contacto
├── front/
│   ├── config.form.php         # Página de configuración del plugin
│   ├── download.php            # Endpoint de generación y descarga
│   └── resource.send.php       # Sirve las plantillas PNG al navegador
├── inc/
│   ├── paths.class.php         # Centraliza todas las rutas de archivos
│   ├── signature.class.php     # Lógica de generación del PNG
│   └── user.class.php          # Tab en el perfil del usuario
└── setup.php                   # Instalación, registro y versión del plugin
```

---

## Flujo interno de generación

```
download.php
  │
  ├── Verifica sesión y control de acceso (propio usuario o admin)
  ├── checkRequirements() → valida plantillas, fuentes, GD y código de país
  │
  └── generatePNG()
        ├── Lee configuración (Facebook, código de país WhatsApp)
        ├── Determina si el usuario tiene celular → selecciona plantilla
        ├── Carga imagen base con imagecreatefrompng()
        ├── Obtiene datos del usuario y su entidad desde GLPI
        ├── Ajusta tamaño de fuente automáticamente para el nombre
        ├── Escribe texto sobre la imagen con imagettftext()
        ├── Si include_qr y hay celular → genera QR con TCPDF y lo superpone
        ├── Guarda PNG temporal en GLPI_TMP_DIR
        └── Retorna ruta del archivo temporal

  → Envía PNG al navegador como descarga
  → Elimina el archivo temporal
```

---

## Almacenamiento

| Tipo | Ubicación |
|------|-----------|
| Plantillas PNG | `{GLPI_PLUGIN_DOC_DIR}/signatures/templates/` |
| Fuentes TTF | `{directorio_plugin}/fonts/` |
| PNG temporal generado | `{GLPI_TMP_DIR}/signature_{userid}.png` (se borra tras la descarga) |
| QR temporal | `{GLPI_TMP_DIR}/signature_qr_{userid}.png` (se borra tras composición) |
| Configuración | Tabla `glpi_configs` con contexto `plugin_signatures` |

---

## Desinstalación

Al desinstalar el plugin desde GLPI, se eliminan automáticamente los valores de configuración (`facebook_page` y `whatsapp_country_code`) de la base de datos.

> Las plantillas PNG almacenadas en `{GLPI_PLUGIN_DOC_DIR}/signatures/templates/` **no se eliminan** automáticamente, por si deseas reinstalar el plugin conservando las plantillas.

---

## Autor

**Edwin Elias Alvarez**  
[https://sontechs.com](https://sontechs.com)

## Licencia

GPLv2.
