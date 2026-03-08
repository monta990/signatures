<?php
declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
   die('Direct access not allowed');
}

function signaturesRibbonSubHeader(string $icon, string $title): void {
    echo "<div class='card-header mb-1 py-1 position-relative'>";
    echo "<div class='ribbon ribbon-bookmark ribbon-top ribbon-start bg-blue s-1'>
            <i class='fs-2x ti {$icon}' aria-hidden='true'></i>
          </div>";
    echo "<h3 class='card-subtitle ms-5 mb-0'>{$title}</h3>";
    echo "</div>";
}

Session::checkRight('config', UPDATE);

global $CFG_GLPI;

$self = Plugin::getWebDir('signatures') . '/front/config.form.php';

/* ========================== CONFIG ========================== */

$maxSize     = 300 * 1024;
$allowedMime = ['image/png'];

$baseDir   = PluginSignaturesPaths::filesDir();
$base1File = PluginSignaturesPaths::base1Path();
$base2File = PluginSignaturesPaths::base2Path();

$hasbase1  = is_readable($base1File);
$hasbase2  = is_readable($base2File);

if (!is_dir($baseDir)) {
   mkdir($baseDir, 0755, true);
}

/* ========================== DELETE ========================== */

if (isset($_POST['delete_base1']) && $hasbase1) {
   unlink($base1File);
   Session::addMessageAfterRedirect(__('Plantilla con celular eliminada', 'signatures'), false, INFO);
   Html::redirect($self);
}

if (isset($_POST['delete_base2']) && $hasbase2) {
   unlink($base2File);
   Session::addMessageAfterRedirect(__('Plantilla sin celular eliminada', 'signatures'), false, INFO);
   Html::redirect($self);
}

/* ========================== SAVE ========================== */

if (isset($_POST['save'])) {

   if (isset($_POST['facebook_page'])) {
      Config::setConfigurationValues(
         'plugin_signatures',
         ['facebook_page' => trim($_POST['facebook_page'])]
      );
   }

   if (isset($_POST['whatsapp_country_code'])) {
      Config::setConfigurationValues(
         'plugin_signatures',
         ['whatsapp_country_code' => preg_replace('/[^0-9]/', '', trim($_POST['whatsapp_country_code']))]
      );
   }

   /* ================= CORREO ================= */
   if (isset($_POST['email_subject'])) {
      Config::setConfigurationValues(
         'plugin_signatures',
         ['email_subject' => trim($_POST['email_subject'])]
      );
   }

   if (isset($_POST['email_body'])) {
      Config::setConfigurationValues(
         'plugin_signatures',
         ['email_body' => trim($_POST['email_body'])]
      );
   }

   if (isset($_POST['email_footer'])) {
      Config::setConfigurationValues(
         'plugin_signatures',
         ['email_footer' => trim($_POST['email_footer'])]
      );
   }
   /* ================= FIN CORREO ================= */

   foreach (['base1' => $base1File, 'base2' => $base2File] as $field => $dest) {

      if (!isset($_FILES[$field]) || !is_uploaded_file($_FILES[$field]['tmp_name'])) {
         continue;
      }

      $tmp  = $_FILES[$field]['tmp_name'];
      $size = $_FILES[$field]['size'];

      $finfo = finfo_open(FILEINFO_MIME_TYPE);
      $mime  = finfo_file($finfo, $tmp);
      finfo_close($finfo);

      if ($size > $maxSize) {
         Session::addMessageAfterRedirect(__('Archivo demasiado grande (Máx. 300 KB)', 'signatures'), false, ERROR);
         Html::redirect($self);
      }

      if (!in_array($mime, $allowedMime, true)) {
         Session::addMessageAfterRedirect(__('Formato inválido, solo PNG', 'signatures'), false, ERROR);
         Html::redirect($self);
      }

      move_uploaded_file($tmp, $dest);
      chmod($dest, 0644);
   }

   /* ================= POSICIONES ================= */
   $posKeys = array_keys(array_filter(
      plugin_signatures_getDefaults(),
      static fn($k) => str_starts_with($k, 'sig_b'),
      ARRAY_FILTER_USE_KEY
   ));
   $posToSave = [];
   foreach ($posKeys as $key) {
      if (isset($_POST[$key]) && $_POST[$key] !== '') {
         $posToSave[$key] = (int)$_POST[$key];
      }
   }
   if (!empty($posToSave)) {
      Config::setConfigurationValues('plugin_signatures', $posToSave);
   }
   /* ================= FIN POSICIONES ================= */

   PluginSignaturesConfig::invalidate();
   Session::addMessageAfterRedirect(__('Configuración guardada correctamente', 'signatures'), false, INFO);
   Html::redirect($self);
}

$config       = PluginSignaturesConfig::getAll();
$facebookPage = $config['facebook_page']        ?? '';
$countryCode  = $config['whatsapp_country_code'] ?? '';
$emailSubject = $config['email_subject']         ?? '';
$emailBody    = $config['email_body']            ?? '';
$emailFooter  = $config['email_footer']          ?? '';

/* ========================== HEADER ========================== */

Html::header(__('Firma de Correo', 'signatures'), $self, 'config', 'plugins');

/* ========================== FORM ========================== */

echo "<form method='post' action='{$self}' enctype='multipart/form-data'>";
echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);

echo "<div class='card mt-2 rounded-0'>";
echo "<div class='card-header mb-3 py-1 border-top position-relative'>";
echo "<div class='ribbon ribbon-bookmark ribbon-top ribbon-start bg-blue s-1'>
        <i class='fs-2x ti ti-settings'></i>
      </div>";
echo "<h4 class='card-title ms-5 mb-0'>" .
      __('Configuración de firma para correo', 'signatures') .
     "</h4>";
echo "</div>";

/* ========================== TABS ========================== */

echo "
<ul class='nav nav-tabs mb-3' role='tablist'>

  <li class='nav-item'>
    <button class='nav-link active'
            data-bs-toggle='tab'
            data-bs-target='#tab-general'
            type='button'>
      <i class='ti ti-settings me-1'></i> General
    </button>
  </li>

  <li class='nav-item'>
    <button class='nav-link'
            data-bs-toggle='tab'
            data-bs-target='#tab-cel'
            type='button'>
      <i class='ti ti-device-mobile me-1'></i> Con celular
    </button>
  </li>

  <li class='nav-item'>
    <button class='nav-link'
            data-bs-toggle='tab'
            data-bs-target='#tab-nocel'
            type='button'>
      <i class='ti ti-phone-off me-1'></i> Sin celular
    </button>
  </li>

  <li class='nav-item'>
    <button class='nav-link'
            data-bs-toggle='tab'
            data-bs-target='#tab-positions'
            type='button'>
      <i class='ti ti-vector-bezier me-1'></i> Posiciones
    </button>
  </li>

</ul>
";

echo "<div class='tab-content'>";

/* =====================================================
 * TAB GENERAL
 * ===================================================== */
echo "<div class='tab-pane fade show active' id='tab-general'>";

/* ── Card: Configuración general ── */
echo "<div class='card mt-2 rounded-0'>";
signaturesRibbonSubHeader('ti-settings', __('Configuración general', 'signatures'));
echo "<div class='card-body'>";

/* FACEBOOK */
echo "<div class='mb-4'>";
echo "<label class='form-label fw-bold'>
        <i class='ti ti-brand-facebook me-2'></i>
        Nombre de la página de Facebook
      </label>";
echo "<input type='text'
             name='facebook_page'
             class='form-control'
             value='" . Html::cleanInputText($facebookPage) . "'
             placeholder='Ej: SontechsMX'>";
echo "<div class='form-text'>
        Solo el nombre, sin URL ni @
      </div>";
echo "</div>";

/* CÓDIGO PAÍS WHATSAPP */
echo "<div class='mb-4'>";
echo "<label class='form-label fw-bold'>
        <i class='ti ti-brand-whatsapp me-2'></i>
        Código de país para QR de Whatsapp
      </label>";
echo "<input type='text'
             name='whatsapp_country_code'
             class='form-control'
             value='" . Html::cleanInputText($countryCode) . "'
             placeholder='Ej: 52'
             maxlength='5'
             inputmode='numeric'
             pattern='[0-9]*'
             oninput='this.value=this.value.replace(/[^0-9]/g,\"\")'>";
echo "<div class='form-text'>
        Solo el código numérico, sin + ni espacios. Ej: 52 (México), para generar el enlace que lleva a Whatsapp en el QR.
      </div>";
echo "</div>";

echo "</div></div>"; // card-body + card general

/* ── Card: Opciones correo ── */
echo "<div class='card mt-2 rounded-0'>";
signaturesRibbonSubHeader('ti-mail-forward', __('Opciones del correo electrónico', 'signatures'));
echo "<div class='card-body'>";

/* PANEL VARIABLES DISPONIBLES */
echo "
<div class='card mb-4 border-info rounded-1' style='background:#f0f8ff;'>
  <div class='card-header py-1 d-flex align-items-center justify-content-between'
       style='cursor:pointer;background:#e0f0ff;'
       data-bs-toggle='collapse'
       data-bs-target='#sigVarsPanel'
       aria-expanded='false'>
    <span class='fw-bold text-info'>
      <i class='ti ti-variable me-1'></i>
      " . __('Variables disponibles', 'signatures') . "
      &nbsp;<code class='ms-2' style='font-size:0.82em;background:#c8e6fa;padding:1px 5px;border-radius:3px;'>
        " . __('Usa **texto** para negrita', 'signatures') . "
      </code>
    </span>
    <i class='ti ti-chevron-down text-info'></i>
  </div>
  <div class='collapse' id='sigVarsPanel'>
    <div class='card-body py-2' style='font-size:0.9em;'>
      <p class='text-muted mb-2' style='font-size:0.85em;'>
        <i class='ti ti-hand-click me-1'></i>" . __('Haz clic en una variable para insertarla en el campo activo.', 'signatures') . "
      </p>
      <table class='table table-borderless table-sm mb-0'>
        <tbody>
          <tr>
            <td>
              <code class='sig-var-badge' data-var='{nombre}'
                    style='background:#ddeeff;padding:2px 6px;border-radius:3px;cursor:pointer;'
                    title='" . htmlspecialchars(__('Clic para insertar', 'signatures'), ENT_QUOTES, 'UTF-8') . "'>{nombre}</code>
            </td>
            <td class='text-muted'>— " . __('Nombre completo del usuario', 'signatures') . "</td>
          </tr>
          <tr>
            <td>
              <code class='sig-var-badge' data-var='{empresa}'
                    style='background:#ddeeff;padding:2px 6px;border-radius:3px;cursor:pointer;'
                    title='" . htmlspecialchars(__('Clic para insertar', 'signatures'), ENT_QUOTES, 'UTF-8') . "'>{empresa}</code>
            </td>
            <td class='text-muted'>— " . __('Nombre de la empresa (configurado en General)', 'signatures') . "</td>
          </tr>
          <tr>
            <td>
              <code class='sig-var-badge' data-var='{fecha}'
                    style='background:#ddeeff;padding:2px 6px;border-radius:3px;cursor:pointer;'
                    title='" . htmlspecialchars(__('Clic para insertar', 'signatures'), ENT_QUOTES, 'UTF-8') . "'>{fecha}</code>
            </td>
            <td class='text-muted'>— " . __('Fecha del día en formato dd/mm/aaaa', 'signatures') . "</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>
";

/* ASUNTO */
echo "<div class='mb-4'>";
echo "<label class='form-label fw-bold'>
        <i class='ti ti-text-size me-2'></i>" .
        __('Asunto del correo', 'signatures') . "
      </label>";
echo "<input type='text'
             name='email_subject'
             class='form-control'
             value='" . Html::cleanInputText($emailSubject) . "'
             placeholder='" . __('Ej: Tu firma de correo corporativa', 'signatures') . "'>";
echo "<div class='form-text'>" .
        __('Puedes usar las variables {nombre}, {empresa} y {fecha}.', 'signatures') .
     "</div>";
echo "</div>";

/* CUERPO */
echo "<div class='mb-4'>";
echo "<label class='form-label fw-bold'>
        <i class='ti ti-align-left me-2'></i>" .
        __('Cuerpo del correo', 'signatures') . "
      </label>";
echo "<textarea name='email_body'
               class='form-control'
               rows='4'
               placeholder='" . __('Ej: Adjunto encontrarás tu firma de correo corporativa. Guárdala y úsala en tu cliente de correo.', 'signatures') . "'>" .
               Html::cleanPostForTextArea($emailBody) .
     "</textarea>";
echo "<div class='form-text'>" .
        __('Puedes usar las variables {nombre}, {empresa} y {fecha}.', 'signatures') .
     "</div>";
echo "</div>";

/* PIE */
echo "<div class='mb-4'>";
echo "<label class='form-label fw-bold'>
        <i class='ti ti-section me-2'></i>" .
        __('Pie del correo', 'signatures') . "
      </label>";
echo "<textarea name='email_footer'
               class='form-control'
               rows='2'
               placeholder='" . __('Ej: Este correo fue generado automáticamente por el sistema GLPI.', 'signatures') . "'>" .
               Html::cleanPostForTextArea($emailFooter) .
     "</textarea>";
echo "<div class='form-text'>" .
        __('Puedes usar las variables {nombre}, {empresa} y {fecha}.', 'signatures') .
     "</div>";
echo "</div>";

/* ── Botón enviar correo de prueba ──────────────────────────────────── */
$_testUrl     = Plugin::getWebDir('signatures') . '/front/send_test.php';
$_coreCfg     = Config::getConfigurationValues('core');
$_mailOk      = ($_coreCfg['use_notifications']    ?? 0) == 1
             && ($_coreCfg['notifications_mailing'] ?? 0) == 1;
$_hasConfig   = !empty(trim($emailSubject)) && !empty(trim($emailBody));
$_btnDisabled = (!$_mailOk || !$_hasConfig);

if (!$_mailOk) {
   $_btnTooltip = __('Servidor de correo de GLPI no configurado', 'signatures');
} elseif (!$_hasConfig) {
   $_btnTooltip = __('Configure el asunto y cuerpo del correo primero', 'signatures');
} else {
   $_btnTooltip = __('Envía un correo de prueba a tu dirección registrada en GLPI', 'signatures');
}

$_btnTooltipEsc = htmlspecialchars($_btnTooltip, ENT_QUOTES, 'UTF-8');
$_btnCls        = $_btnDisabled
   ? "btn btn-warning disabled' aria-disabled='true' style='pointer-events:none;opacity:.65"
   : "btn btn-warning";
$_testCsrfToken = Session::getNewCSRFToken();

echo "<div class='d-flex align-items-center gap-3 flex-wrap mt-3'>
   <span data-bs-toggle='tooltip' title='{$_btnTooltipEsc}' class='d-inline-block'>
      <button type='submit' form='sig-test-mail-form' class='{$_btnCls}'>
         <i class='ti ti-send me-2'></i>"
         . __('Enviar correo de prueba', 'signatures') .
      "</button>
   </span>
   <span class='text-muted' style='font-size:0.85em;'>
      <i class='ti ti-info-circle me-1'></i>"
      . __('El correo se enviará a la dirección registrada en tu perfil de GLPI.', 'signatures') .
   "</span>
</div>";

echo "</div></div>"; // card-body + card correo
echo "</div>"; // fin tab-general

/* =====================================================
 * TAB CON CELULAR
 * ===================================================== */
echo "<div class='tab-pane fade' id='tab-cel'>";
echo "<div class='card mt-2 rounded-0'>";
signaturesRibbonSubHeader('ti-device-mobile', __('Plantilla con celular', 'signatures'));
echo "<div class='card-body'>";

if ($hasbase1) {
   $url = PluginSignaturesPaths::base1Url();
   echo "<div class='mb-4'>";
   echo "<label class='fw-bold'>" . __('Actual', 'signatures') . "</label><br>";
   echo "<a href='{$url}' download='plantilla_con_celular.png'>";
   echo "<img src='{$url}&t=" . time() . "' style='max-width:100%;border:1px solid #ccc'>";
   echo "</a><br><br>";
   echo "<button type='button' name='delete_base1' class='btn btn-danger'
            onclick=\"this.form.submit(); this.form.delete_base1.value=1;\">
            <i class='ti ti-trash'></i> " . __('Eliminar', 'signatures') . "
         </button>";
   echo "</div>";
}

echo "<label class='fw-bold'>" . __('Cargar nueva', 'signatures') . "</label>";
echo "<input type='file' name='base1' class='form-control' accept='image/png'
       onchange='preview(this,\"new-base1-preview\",\"wrap1\")'>";
echo "<div id='wrap1' class='d-none mt-2'>
        <img id='new-base1-preview' style='max-width:100%;border:1px dashed #999'>
      </div>";

echo "</div></div>"; // card-body + card
echo "</div>"; // fin tab-cel

/* =====================================================
 * TAB SIN CELULAR
 * ===================================================== */
echo "<div class='tab-pane fade' id='tab-nocel'>";
echo "<div class='card mt-2 rounded-0'>";
signaturesRibbonSubHeader('ti-phone-off', __('Plantilla sin celular', 'signatures'));
echo "<div class='card-body'>";

if ($hasbase2) {
   $url = PluginSignaturesPaths::base2Url();
   echo "<div class='mb-4'>";
   echo "<label class='fw-bold'>" . __('Actual', 'signatures') . "</label><br>";
   echo "<a href='{$url}' download='plantilla_sin_celular.png'>";
   echo "<img src='{$url}&t=" . time() . "' style='max-width:100%;border:1px solid #ccc'>";
   echo "</a><br><br>";
   echo "<button type='button' name='delete_base2' class='btn btn-danger'
            onclick=\"this.form.submit(); this.form.delete_base2.value=1;\">
            <i class='ti ti-trash'></i> " . __('Eliminar', 'signatures') . "
         </button>";
   echo "</div>";
}

echo "<label class='fw-bold'>" . __('Cargar nueva', 'signatures') . "</label>";
echo "<input type='file' name='base2' class='form-control' accept='image/png'
       onchange='preview(this,\"new-base2-preview\",\"wrap2\")'>";
echo "<div id='wrap2' class='d-none mt-2'>
        <img id='new-base2-preview' style='max-width:100%;border:1px dashed #999'>
      </div>";

echo "</div></div>"; // card-body + card
echo "</div>"; // fin tab-nocel

/* =====================================================
 * TAB POSICIONES — Editor visual drag & drop
 * ===================================================== */
echo "<div class='tab-pane fade' id='tab-positions'>";

// ── Obtener datos reales del usuario actual ────────────────────────────
$_adminId  = (int)Session::getLoginUserID();
$_adminUser = new User();
$_adminUser->getFromDB($_adminId);

$_uName     = $_adminUser->getFriendlyName() ?: 'Nombre Apellido';
$_uEmail    = 'correo@empresa.com';
$_uMobile   = $_adminUser->fields['mobile'] ?? '55 1234 5678';
$_uPhone    = $_adminUser->fields['phone']  ?? '';
$_uPhone2   = $_adminUser->fields['phone2'] ?? '';

$_uEmails = (new UserEmail())->find(['users_id' => $_adminId, 'is_default' => 1], [], 1);
if (!empty($_uEmails)) {
   $_row    = reset($_uEmails);
   $_uEmail = $_row['email'] ?? $_uEmail;
}

if (empty($_uMobile)) { $_uMobile = '55 1234 5678'; }

$_uTitulo  = __('No especificado', 'signatures');
if (!empty($_adminUser->fields['usertitles_id'])) {
   $_uTitulo = Dropdown::getDropdownName('glpi_usertitles', (int)$_adminUser->fields['usertitles_id']);
}

$_entityPos = new Entity();
$_phoneEnt  = '';
$_web       = '';
if ($_entityPos->getFromDB((int)($_adminUser->fields['entities_id'] ?? 0))) {
   $_phoneEnt = (string)$_entityPos->getField('phonenumber');
   $_web      = (string)$_entityPos->getField('website');
}
if (empty($_phoneEnt)) { $_phoneEnt = '55 9876 5432'; }
if (empty($_web))      { $_web = 'www.empresa.com'; }

$_extraLabel = '';
$_extraPhone = '';
if ($_uPhone2 !== '')       { $_extraLabel = 'Oficina: '; $_extraPhone = $_uPhone2; }
elseif ($_uPhone !== '')    { $_extraLabel = 'Ext: ';    $_extraPhone = $_uPhone; }
if (empty($_extraPhone))    { $_extraLabel = 'Ext: '; $_extraPhone = '123'; }

// ── URLs de fuentes y plantillas ──────────────────────────────────────
$_pluginWebDir = Plugin::getWebDir('signatures');
$_fontBlackUrl = $_pluginWebDir . '/fonts/AvenirBlack.ttf';
$_fontRomanUrl = $_pluginWebDir . '/fonts/AvenirRoman.ttf';
$_base1Url     = PluginSignaturesPaths::base1Url();
$_base2Url     = PluginSignaturesPaths::base2Url();

// ── Leer posiciones actuales desde config ─────────────────────────────
$_c = PluginSignaturesConfig::getAll();
$_D = plugin_signatures_getDefaults();
$_pos = static function (string $key) use ($_c, $_D): int {
   return (int)(($_c[$key] ?? '') !== '' ? $_c[$key] : ($_D[$key] ?? 0));
};

// ── Datos de campos por plantilla ─────────────────────────────────────
// [ id, label, x, y, size, font (black|roman), color (white|black), sample_text ]
$_fieldsB1 = [
   ['nombre',   __('Nombre',         'signatures'), $_pos('sig_b1_nombre_x'),   $_pos('sig_b1_nombre_y'),   $_pos('sig_b1_nombre_size'),   'black', 'white',  $_uName],
   ['titulo',   __('Título',         'signatures'), $_pos('sig_b1_titulo_x'),   $_pos('sig_b1_titulo_y'),   $_pos('sig_b1_titulo_size'),   'black', 'white',  $_uTitulo],
   ['email',    __('Correo',         'signatures'), $_pos('sig_b1_email_x'),    $_pos('sig_b1_email_y'),    $_pos('sig_b1_email_size'),    'roman', 'black',  $_uEmail],
   ['mobile',   __('Celular',        'signatures'), $_pos('sig_b1_mobile_x'),   $_pos('sig_b1_mobile_y'),   $_pos('sig_b1_mobile_size'),   'roman', 'black',  $_uMobile],
   ['tel',      __('Tel. entidad',   'signatures'), $_pos('sig_b1_tel_x'),      $_pos('sig_b1_tel_y'),      $_pos('sig_b1_tel_size'),      'roman', 'black',  $_phoneEnt],
   ['ext',      __('Ext/Oficina',    'signatures'), $_pos('sig_b1_ext_x'),      $_pos('sig_b1_ext_y'),      $_pos('sig_b1_ext_size'),      'roman', 'black',  $_extraLabel . $_extraPhone],
   ['facebook', __('Facebook',       'signatures'), $_pos('sig_b1_facebook_x'), $_pos('sig_b1_facebook_y'), $_pos('sig_b1_facebook_size'), 'roman', 'black',  $facebookPage ?: 'cyalimentos'],
   ['web',      __('Web',            'signatures'), $_pos('sig_b1_web_x'),      $_pos('sig_b1_web_y'),      $_pos('sig_b1_web_size'),      'roman', 'black',  $_web],
   ['qr',       __('QR WhatsApp',    'signatures'), $_pos('sig_b1_qr_x'),       $_pos('sig_b1_qr_y'),       0,                            'roman', 'black',  '▣ QR'],
];

$_fieldsB2 = [
   ['nombre',   __('Nombre',         'signatures'), $_pos('sig_b2_nombre_x'),   $_pos('sig_b2_nombre_y'),   $_pos('sig_b2_nombre_size'),   'black', 'white',  $_uName],
   ['titulo',   __('Título',         'signatures'), $_pos('sig_b2_titulo_x'),   $_pos('sig_b2_titulo_y'),   $_pos('sig_b2_titulo_size'),   'black', 'white',  $_uTitulo],
   ['email',    __('Correo',         'signatures'), $_pos('sig_b2_email_x'),    $_pos('sig_b2_email_y'),    $_pos('sig_b2_email_size'),    'roman', 'black',  $_uEmail],
   ['tel',      __('Tel. entidad',   'signatures'), $_pos('sig_b2_tel_x'),      $_pos('sig_b2_tel_y'),      $_pos('sig_b2_tel_size'),      'roman', 'black',  $_phoneEnt],
   ['ext',      __('Ext/Oficina',    'signatures'), $_pos('sig_b2_ext_x'),      $_pos('sig_b2_ext_y'),      $_pos('sig_b2_ext_size'),      'roman', 'black',  $_extraLabel . $_extraPhone],
   ['facebook', __('Facebook',       'signatures'), $_pos('sig_b2_facebook_x'), $_pos('sig_b2_facebook_y'), $_pos('sig_b2_facebook_size'), 'roman', 'black',  $facebookPage ?: 'cyalimentos'],
   ['web',      __('Web',            'signatures'), $_pos('sig_b2_web_x'),      $_pos('sig_b2_web_y'),      $_pos('sig_b2_web_size'),      'roman', 'black',  $_web],
];

// ── Renderizar editor ─────────────────────────────────────────────────
$_renderEditor = static function (
   string $baseId,
   string $title,
   string $bgUrl,
   array  $fields,
   bool   $hasTemplate
) use ($_fontBlackUrl, $_fontRomanUrl): void {

   $ASCENT_FACTOR = 0.72; // fracción del tamaño que es ascenso sobre baseline

   echo "<div class='card mt-2 rounded-0'>";
   signaturesRibbonSubHeader('ti-vector-bezier', htmlspecialchars($title, ENT_QUOTES, 'UTF-8'));
   echo "<div class='card-body'>";

   if (!$hasTemplate) {
      echo "<div class='alert alert-warning'><i class='ti ti-alert-triangle me-2'></i>"
         . __('No hay plantilla cargada para esta configuración. Carga una en la pestaña correspondiente.', 'signatures')
         . "</div>";
      echo "</div></div>"; // card-body + card (early return sin template)
      return;
   }

   echo "<p class='text-muted small mb-2'>
      <i class='ti ti-hand-move me-1'></i>"
      . __('Arrastra cada campo a su posición. Usa los inputs de tamaño para ajustar el tamaño de fuente. Guarda con el botón Guardar.', 'signatures') .
   "</p>";

   // Contenedor del editor (posición relativa, tamaño natural de la imagen)
   echo "<div class='sig-editor-wrap' id='editor-{$baseId}' style='position:relative;display:inline-block;overflow:visible;'>";
   echo "<img src='{$bgUrl}&t=" . time() . "'
              id='img-{$baseId}'
              style='display:block;max-width:100%;'
              draggable='false'>";

   foreach ($fields as [$fieldId, $label, $gdX, $gdY, $gdSize, $fontType, $color, $sample]) {
      $isQr    = ($fieldId === 'qr');
      $fontCss = $fontType === 'black' ? 'AvenirBlack' : 'AvenirRoman';
      $textColor = $color === 'white' ? '#fff' : '#000';
      $inputKey  = "sig_{$baseId}_{$fieldId}";

      // Convertir coordenadas GD (baseline) a CSS (top de la caja)
      // css_top = gd_y - gdSize * ASCENT_FACTOR
      $cssTop  = $isQr ? $gdY : max(0, (int)round($gdY - $gdSize * $ASCENT_FACTOR));
      $cssLeft = $gdX;
      $cssFontSize = $gdSize; // 1:1 aproximación inicial

      if ($isQr) {
         // QR: caja fija de 100x100px
         echo "<div class='sig-field sig-field-qr'
                    id='field-{$baseId}-{$fieldId}'
                    data-base='{$baseId}'
                    data-field='{$fieldId}'
                    data-is-qr='1'
                    style='position:absolute;
                           left:{$cssLeft}px;top:{$cssTop}px;
                           width:100px;height:100px;
                           border:2px dashed rgba(255,140,0,0.8);
                           background:rgba(255,140,0,0.15);
                           cursor:grab;
                           display:flex;align-items:center;justify-content:center;
                           font-size:13px;color:rgba(255,140,0,0.9);
                           user-select:none;'>
                 <i class='ti ti-qrcode' style='font-size:24px;'></i>
              </div>";
      } else {
         $labelEsc  = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
         $sampleEsc = htmlspecialchars($sample, ENT_QUOTES, 'UTF-8');
         echo "<div class='sig-field'
                    id='field-{$baseId}-{$fieldId}'
                    data-base='{$baseId}'
                    data-field='{$fieldId}'
                    data-font-size='{$gdSize}'
                    title='{$labelEsc}'
                    style='position:absolute;
                           left:{$cssLeft}px;top:{$cssTop}px;
                           font-family:{$fontCss},sans-serif;
                           font-size:{$cssFontSize}px;
                           color:{$textColor};
                           white-space:nowrap;
                           cursor:grab;
                           padding:1px 3px;
                           border:1px dashed rgba(255,140,0,0.6);
                           background:rgba(255,140,0,0.08);
                           user-select:none;
                           line-height:1;'>
                    {$sampleEsc}
              </div>";
      }

      // Inputs ocultos para X e Y (en coordenadas GD)
      $inputBase = "sig_{$baseId}_{$fieldId}";
      echo "<input type='hidden' name='{$inputBase}_x' id='inp-{$baseId}-{$fieldId}-x' value='{$gdX}'>";
      echo "<input type='hidden' name='{$inputBase}_y' id='inp-{$baseId}-{$fieldId}-y' value='{$gdY}'>";
      if (!$isQr) {
         echo "<input type='hidden' name='{$inputBase}_size' id='inp-{$baseId}-{$fieldId}-size' value='{$gdSize}'>";
      }
   }

   echo "</div>"; // .sig-editor-wrap

   // Tabla de controles de tamaño de fuente
   echo "<div class='mt-3'>";
   echo "<table class='table table-sm table-bordered' style='max-width:420px'>";
   echo "<thead><tr>
      <th>" . __('Campo', 'signatures') . "</th>
      <th style='width:100px'>" . __('Tamaño (px)', 'signatures') . "</th>
      <th style='width:80px'>" . __('Posición', 'signatures') . "</th>
   </tr></thead><tbody>";

   foreach ($fields as [$fieldId, $label, $gdX, $gdY, $gdSize]) {
      if ($fieldId === 'qr') continue; // QR no tiene tamaño de fuente
      $inputBase = "sig_{$baseId}_{$fieldId}";
      echo "<tr>
         <td><small>{$label}</small></td>
         <td>
            <input type='number' min='6' max='80'
                   class='form-control form-control-sm sig-size-input'
                   data-base='{$baseId}' data-field='{$fieldId}'
                   value='{$gdSize}' style='width:70px;'>
         </td>
         <td>
            <small class='text-muted sig-pos-display' id='pos-{$baseId}-{$fieldId}'>
               {$gdX},{$gdY}
            </small>
         </td>
      </tr>";
   }

   echo "</tbody></table></div>";

   // Botón reset
   echo "<button type='button' class='btn btn-sm btn-outline-secondary sig-reset-btn' data-base='{$baseId}'>
      <i class='ti ti-refresh me-1'></i>" . __('Restaurar posiciones por defecto', 'signatures') . "
   </button>";

   echo "</div></div>"; // card-body + card editor
};

$_renderEditor('b1',
   'Plantilla con celular — Editor de posiciones',
   $_base1Url,
   $_fieldsB1,
   $hasbase1
);

$_renderEditor('b2',
   'Plantilla sin celular — Editor de posiciones',
   $_base2Url,
   $_fieldsB2,
   $hasbase2
);

echo "</div>"; // tab-pane positions

echo "</div>"; // tab-content

/* FOOTER — banner de cambios sin guardar + card-footer del card exterior */
echo "<div id='sig-unsaved-banner' class='alert alert-warning d-none mb-0 mx-0 py-2 px-3 rounded-0' style='font-size:0.88em;'>
   <i class='ti ti-alert-triangle me-2'></i>"
   . __('Hay cambios en las posiciones sin guardar. Haz clic en Guardar para aplicarlos.', 'signatures') .
"</div>";
echo "<div class='card-footer text-end'>";
echo "<button type='submit' name='save' class='btn btn-primary'>"
   . "<i class='ti ti-device-floppy me-1'></i>"
   . __('Guardar', 'signatures')
   . "</button>";
echo "</div>";

echo "</div>"; // fin card exterior
echo "</form>"; // cierre del form principal

// Form oculto del correo de prueba — FUERA del form principal (evita anidamiento HTML inválido)
// El <button form='sig-test-mail-form'> declarado dentro del tab-general se asocia a este form via HTML5.
echo "<form id='sig-test-mail-form' method='post' action='" . htmlspecialchars($_testUrl, ENT_QUOTES, 'UTF-8') . "' style='display:none;'>
   <input type='hidden' name='_glpi_csrf_token' value='{$_testCsrfToken}'>
</form>";

/* ========================== JS PREVIEW + EDITOR ========================== */
$_defaults_js = json_encode([
   'b1' => [
      'nombre'   => ['x'=>20,  'y'=>75,  'size'=>40],
      'titulo'   => ['x'=>20,  'y'=>104, 'size'=>11],
      'email'    => ['x'=>63,  'y'=>138, 'size'=>11],
      'mobile'   => ['x'=>63,  'y'=>161, 'size'=>11],
      'tel'      => ['x'=>185, 'y'=>161, 'size'=>11],
      'ext'      => ['x'=>283, 'y'=>161, 'size'=>11],
      'facebook' => ['x'=>63,  'y'=>183, 'size'=>11],
      'web'      => ['x'=>185, 'y'=>183, 'size'=>11],
      'qr'       => ['x'=>560, 'y'=>130],
   ],
   'b2' => [
      'nombre'   => ['x'=>20,  'y'=>75,  'size'=>40],
      'titulo'   => ['x'=>20,  'y'=>104, 'size'=>11],
      'email'    => ['x'=>63,  'y'=>138, 'size'=>11],
      'tel'      => ['x'=>63,  'y'=>161, 'size'=>11],
      'ext'      => ['x'=>160, 'y'=>161, 'size'=>11],
      'facebook' => ['x'=>63,  'y'=>183, 'size'=>11],
      'web'      => ['x'=>185, 'y'=>183, 'size'=>11],
   ],
], JSON_UNESCAPED_UNICODE);

$_fontBlackUrlJs = htmlspecialchars($_fontBlackUrl, ENT_QUOTES, 'UTF-8');
$_fontRomanUrlJs = htmlspecialchars($_fontRomanUrl, ENT_QUOTES, 'UTF-8');

echo <<<HTML
<style>
@font-face {
   font-family: 'AvenirBlack';
   src: url('{$_fontBlackUrlJs}');
   font-weight: normal; font-style: normal;
}
@font-face {
   font-family: 'AvenirRoman';
   src: url('{$_fontRomanUrlJs}');
   font-weight: normal; font-style: normal;
}
.sig-field { touch-action: none; }
.sig-field:hover { border-color: rgba(255,100,0,0.9) !important; }
.sig-editor-wrap { cursor: default; }
.sig-var-badge:hover { background:#b8d8ff !important; outline:1px solid #6ab0ff; }
</style>
<script>
const ASCENT = 0.72;
const SIG_DEFAULTS = {$_defaults_js};

// ── Estado de cambios sin guardar ──────────────────────────────────────
let _positionsDirty = false;

function markPositionsDirty() {
   if (_positionsDirty) return;
   _positionsDirty = true;
   const tabBtn = document.querySelector('[data-bs-target="#tab-positions"]');
   if (tabBtn && !tabBtn.querySelector('.sig-dirty-dot')) {
      const dot = document.createElement('span');
      dot.className = 'sig-dirty-dot badge bg-warning text-dark ms-1 p-1';
      dot.style.fontSize = '0.6em';
      dot.title = 'Cambios sin guardar';
      dot.textContent = '●';
      tabBtn.appendChild(dot);
   }
   const banner = document.getElementById('sig-unsaved-banner');
   if (banner) banner.classList.remove('d-none');
}

function clearPositionsDirty() {
   _positionsDirty = false;
   document.querySelectorAll('.sig-dirty-dot').forEach(el => el.remove());
   const banner = document.getElementById('sig-unsaved-banner');
   if (banner) banner.classList.add('d-none');
}

// Limpiar al guardar el form principal
document.querySelector('form[method="post"]')?.addEventListener('submit', clearPositionsDirty);

// ── Drag & drop ────────────────────────────────────────────────────────
(function() {
   let dragging = null, ox = 0, oy = 0, startL = 0, startT = 0;

   document.addEventListener('mousedown', e => {
      const el = e.target.closest('.sig-field');
      if (!el) return;
      e.preventDefault();
      dragging = el;
      ox = e.clientX; oy = e.clientY;
      startL = el.offsetLeft; startT = el.offsetTop;
      el.style.cursor = 'grabbing';
      el.style.zIndex = 999;
   });

   document.addEventListener('mousemove', e => {
      if (!dragging) return;
      const dx = e.clientX - ox;
      const dy = e.clientY - oy;
      const newL = Math.max(0, startL + dx);
      const newT = Math.max(0, startT + dy);
      dragging.style.left = newL + 'px';
      dragging.style.top  = newT + 'px';
      syncInputs(dragging, newL, newT);
      markPositionsDirty();
   });

   document.addEventListener('mouseup', () => {
      if (!dragging) return;
      dragging.style.cursor = 'grab';
      dragging.style.zIndex = '';
      dragging = null;
   });
})();

function syncInputs(el, cssLeft, cssTop) {
   const base    = el.dataset.base;
   const field   = el.dataset.field;
   const isQr    = el.dataset.isQr === '1';
   const gdSize  = isQr ? 0 : parseInt(el.dataset.fontSize || '11');
   const gdX     = Math.round(cssLeft);
   const gdY     = isQr ? Math.round(cssTop) : Math.round(cssTop + gdSize * ASCENT);

   const inpX = document.getElementById('inp-' + base + '-' + field + '-x');
   const inpY = document.getElementById('inp-' + base + '-' + field + '-y');
   if (inpX) inpX.value = gdX;
   if (inpY) inpY.value = gdY;

   const pos = document.getElementById('pos-' + base + '-' + field);
   if (pos) pos.textContent = gdX + ',' + gdY;
}

// ── Cambio de tamaño desde input ───────────────────────────────────────
document.addEventListener('input', e => {
   const inp = e.target;
   if (!inp.classList.contains('sig-size-input')) return;
   const base  = inp.dataset.base;
   const field = inp.dataset.field;
   const size  = parseInt(inp.value) || 11;

   const el = document.getElementById('field-' + base + '-' + field);
   if (!el) return;

   el.style.fontSize   = size + 'px';
   el.dataset.fontSize = size;

   const inpS = document.getElementById('inp-' + base + '-' + field + '-size');
   if (inpS) inpS.value = size;

   syncInputs(el, el.offsetLeft, el.offsetTop);
   markPositionsDirty();
});

// ── Reset a defaults ────────────────────────────────────────────────────
document.addEventListener('click', e => {
   const btn = e.target.closest('.sig-reset-btn');
   if (!btn) return;
   const base = btn.dataset.base;
   const defs = SIG_DEFAULTS[base];
   if (!defs) return;

   Object.entries(defs).forEach(([field, coords]) => {
      const el = document.getElementById('field-' + base + '-' + field);
      if (!el) return;
      const isQr   = el.dataset.isQr === '1';
      const size   = coords.size || 11;
      const cssTop = isQr ? coords.y : Math.max(0, Math.round(coords.y - size * ASCENT));

      el.style.left = coords.x + 'px';
      el.style.top  = cssTop + 'px';
      if (!isQr) {
         el.style.fontSize   = size + 'px';
         el.dataset.fontSize = size;
         const inpS = document.getElementById('inp-' + base + '-' + field + '-size');
         if (inpS) inpS.value = size;
         const sizeInput = document.querySelector('.sig-size-input[data-base="' + base + '"][data-field="' + field + '"]');
         if (sizeInput) sizeInput.value = size;
      }
      syncInputs(el, coords.x, cssTop);
   });
   markPositionsDirty();
});

// ── Preview de plantillas (tabs Con/Sin celular) ──────────────────────
function preview(input, imgId, wrapId) {
   const file = input.files[0];
   if (!file) return;
   if (file.type !== 'image/png') {
      alert('Solo PNG permitido');
      input.value = '';
      return;
   }
   const reader = new FileReader();
   reader.onload = e => {
      document.getElementById(imgId).src = e.target.result;
      document.getElementById(wrapId).classList.remove('d-none');
   };
   reader.readAsDataURL(file);
}

// ── Badges de variables clickeables ───────────────────────────────────
let _lastFocusedField = null;

['email_subject', 'email_body', 'email_footer'].forEach(name => {
   const el = document.querySelector('[name="' + name + '"]');
   if (el) el.addEventListener('focus', () => { _lastFocusedField = el; });
});

document.addEventListener('click', e => {
   const badge = e.target.closest('.sig-var-badge');
   if (!badge) return;
   const varText = badge.dataset.var;
   const target  = _lastFocusedField || document.querySelector('[name="email_body"]');
   if (!target) return;

   const start = target.selectionStart ?? target.value.length;
   const end   = target.selectionEnd   ?? target.value.length;
   target.value = target.value.slice(0, start) + varText + target.value.slice(end);
   target.selectionStart = target.selectionEnd = start + varText.length;
   target.focus();
});
</script>
HTML;

Html::footer();
