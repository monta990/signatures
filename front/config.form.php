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

// Fix 7: volver al tab activo tras guardar
$activeTab = in_array($_POST['active_tab'] ?? '', ['general','cel','nocel','positions'], true)
   ? $_POST['active_tab']
   : 'general';

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
         $val = (int)$_POST[$key];
         // Coordenadas X/Y: 0..9999; tamaños de fuente: 1..200
         if (str_ends_with($key, '_size')) {
            $val = max(1, min(200, $val));
         } else {
            $val = max(0, min(9999, $val));
         }
         $posToSave[$key] = $val;
      }
   }
   if (!empty($posToSave)) {
      Config::setConfigurationValues('plugin_signatures', $posToSave);
   }
   /* ================= FIN POSICIONES ================= */

   PluginSignaturesConfig::invalidate();
   Session::addMessageAfterRedirect(__('Configuración guardada correctamente', 'signatures'), false, INFO);
   Html::redirect($self . '#tab-' . $activeTab);
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

echo "<form method='post' action='{$self}' enctype='multipart/form-data'>
   <input type='hidden' name='active_tab' id='active_tab_input' value='general'>";
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
<ul class='nav nav-tabs mb-3 sig-tab-nav' role='tablist'>

  <li class='nav-item'>
    <button class='nav-link active'
            data-bs-toggle='tab'
            data-bs-target='#tab-general'
            type='button'>
      <i class='ti ti-settings me-1'></i> " . __('General', 'signatures') . "
    </button>
  </li>

  <li class='nav-item'>
    <button class='nav-link'
            data-bs-toggle='tab'
            data-bs-target='#tab-cel'
            type='button'>
      <i class='ti ti-device-mobile me-1'></i> " . __('Con celular', 'signatures') . "
    </button>
  </li>

  <li class='nav-item'>
    <button class='nav-link'
            data-bs-toggle='tab'
            data-bs-target='#tab-nocel'
            type='button'>
      <i class='ti ti-phone-off me-1'></i> " . __('Sin celular', 'signatures') . "
    </button>
  </li>

  <li class='nav-item'>
    <button class='nav-link'
            id='btn-tab-positions'
            data-bs-toggle='tab'
            data-bs-target='#tab-positions'
            type='button'>
      <i class='ti ti-vector-bezier me-1'></i> " . __('Posiciones', 'signatures') . "
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
        " . __('Nombre de la página de Facebook', 'signatures') . "
      </label>";
echo "<input type='text'
             name='facebook_page'
             class='form-control'
             value='" . Html::cleanInputText($facebookPage) . "'
             placeholder='" . htmlspecialchars(__('Ej: SontechsMX', 'signatures'), ENT_QUOTES, 'UTF-8') . "'>";
echo "<div class='form-text'>" . __('Solo el nombre, sin URL ni @', 'signatures') . "</div>";
echo "</div>";

/* CÓDIGO PAÍS WHATSAPP */
echo "<div class='mb-4'>";
echo "<label class='form-label fw-bold'>
        <i class='ti ti-brand-whatsapp me-2'></i>
        " . __('Código de país para QR de Whatsapp', 'signatures') . "
      </label>";
echo "<input type='text'
             name='whatsapp_country_code'
             class='form-control'
             value='" . Html::cleanInputText($countryCode) . "'
             placeholder='" . htmlspecialchars(__('Ej: 52', 'signatures'), ENT_QUOTES, 'UTF-8') . "'
             maxlength='5'
             inputmode='numeric'
             pattern='[0-9]*'
             oninput='this.value=this.value.replace(/[^0-9]/g,\"\")'>";
echo "<div class='form-text'>" . __('Solo el código numérico, sin + ni espacios. Ej: 52 (México), para generar el enlace que lleva a Whatsapp en el QR.', 'signatures') . "</div>";
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
        <b>**" . __('negrita', 'signatures') . "**</b>
        &nbsp;·&nbsp;
        <i>*" . __('cursiva', 'signatures') . "*</i>
        &nbsp;·&nbsp;
        <u>__" . __('subrayado', 'signatures') . "__</u>
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
             id='sig-email-subject'
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
echo "<div class='sig-format-toolbar mb-1 d-flex gap-1 align-items-center'>
   <button type='button' class='btn btn-sm btn-outline-secondary sig-fmt-btn' data-wrap='**'
           title='" . __('Negrita — **texto**', 'signatures') . "'>
      <b>B</b>&nbsp;<small class='fw-normal opacity-75'>**</small>
   </button>
   <button type='button' class='btn btn-sm btn-outline-secondary sig-fmt-btn' data-wrap='*'
           title='" . __('Cursiva — *texto*', 'signatures') . "'>
      <i>I</i>&nbsp;<small class='fw-normal opacity-75'>*</small>
   </button>
   <button type='button' class='btn btn-sm btn-outline-secondary sig-fmt-btn' data-wrap='__'
           title='" . __('Subrayado — __texto__', 'signatures') . "'>
      <u>U</u>&nbsp;<small class='fw-normal opacity-75'>__</small>
   </button>
</div>";
echo "<textarea name='email_body' id='sig-email-body'
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
echo "<div class='sig-format-toolbar mb-1 d-flex gap-1 align-items-center'>
   <button type='button' class='btn btn-sm btn-outline-secondary sig-fmt-btn' data-wrap='**'
           title='" . __('Negrita — **texto**', 'signatures') . "'>
      <b>B</b>&nbsp;<small class='fw-normal opacity-75'>**</small>
   </button>
   <button type='button' class='btn btn-sm btn-outline-secondary sig-fmt-btn' data-wrap='*'
           title='" . __('Cursiva — *texto*', 'signatures') . "'>
      <i>I</i>&nbsp;<small class='fw-normal opacity-75'>*</small>
   </button>
   <button type='button' class='btn btn-sm btn-outline-secondary sig-fmt-btn' data-wrap='__'
           title='" . __('Subrayado — __texto__', 'signatures') . "'>
      <u>U</u>&nbsp;<small class='fw-normal opacity-75'>__</small>
   </button>
</div>";
echo "<textarea name='email_footer' id='sig-email-footer'
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
$_testUrl     = Plugin::getWebDir('signatures') . '/front/send.php';
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
$_btnCls = $_btnDisabled
   ? "btn btn-outline-secondary disabled' aria-disabled='true' style='pointer-events:none;opacity:.65"
   : "btn btn-outline-secondary";
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
   $url       = PluginSignaturesPaths::base1Url();
   $cacheBust = filemtime($base1File);
   $csrf1     = Session::getNewCSRFToken();
   echo "<div class='mb-4'>";
   echo "<label class='fw-bold'>" . __('Actual', 'signatures') . "</label><br>";
   echo "<a href='{$url}' download='plantilla_con_celular.png'>";
   echo "<img src='{$url}&t={$cacheBust}' style='max-width:100%;border:1px solid #ccc'>";
   echo "</a><br><br>";
   // #6: form independiente para el botón eliminar (evita enviar toda la config al mismo tiempo)
   echo "<form method='post' action='{$self}' style='display:inline;'>
            <input type='hidden' name='_glpi_csrf_token' value='{$csrf1}'>
            <button type='submit' name='delete_base1' value='1' class='btn btn-danger'>
               <i class='ti ti-trash'></i> " . __('Eliminar', 'signatures') . "
            </button>
         </form>";
   echo "</div>";
}

echo "<label class='fw-bold'>" . __('Cargar nueva', 'signatures') . "</label>";
echo "<input type='file' name='base1' class='form-control' accept='image/png'
       onchange='preview(this,\"new-base1-preview\",\"wrap1\")'>";
echo "<div class='form-text'>" . __('Solo PNG · Máx. 300 KB · Dimensiones recomendadas: 650×250 px', 'signatures') . "</div>";
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
   $url       = PluginSignaturesPaths::base2Url();
   $cacheBust = filemtime($base2File);
   $csrf2     = Session::getNewCSRFToken();
   echo "<div class='mb-4'>";
   echo "<label class='fw-bold'>" . __('Actual', 'signatures') . "</label><br>";
   echo "<a href='{$url}' download='plantilla_sin_celular.png'>";
   echo "<img src='{$url}&t={$cacheBust}' style='max-width:100%;border:1px solid #ccc'>";
   echo "</a><br><br>";
   // #6: form independiente para el botón eliminar
   echo "<form method='post' action='{$self}' style='display:inline;'>
            <input type='hidden' name='_glpi_csrf_token' value='{$csrf2}'>
            <button type='submit' name='delete_base2' value='1' class='btn btn-danger'>
               <i class='ti ti-trash'></i> " . __('Eliminar', 'signatures') . "
            </button>
         </form>";
   echo "</div>";
}

echo "<label class='fw-bold'>" . __('Cargar nueva', 'signatures') . "</label>";
echo "<input type='file' name='base2' class='form-control' accept='image/png'
       onchange='preview(this,\"new-base2-preview\",\"wrap2\")'>";
echo "<div class='form-text'>" . __('Solo PNG · Máx. 300 KB · Dimensiones recomendadas: 650×250 px', 'signatures') . "</div>";
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

$_uName     = $_adminUser->getFriendlyName() ?: __('Nombre Apellido', 'signatures');
$_uEmail    = __('correo@empresa.com', 'signatures');
$_uMobile   = $_adminUser->fields['mobile'] ?? '';
$_uPhone    = $_adminUser->fields['phone']  ?? '';
$_uPhone2   = $_adminUser->fields['phone2'] ?? '';

$_uEmails = (new UserEmail())->find(['users_id' => $_adminId, 'is_default' => 1], [], 1);
if (!empty($_uEmails)) {
   $_row    = reset($_uEmails);
   $_uEmail = $_row['email'] ?? $_uEmail;
}

if (empty($_uMobile)) { $_uMobile = __('55 1234 5678', 'signatures'); }

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
if (empty($_phoneEnt)) { $_phoneEnt = __('55 9876 5432', 'signatures'); }
if (empty($_web))      { $_web = __('www.empresa.com', 'signatures'); }

$_extraLabel = '';
$_extraPhone = '';
if ($_uPhone2 !== '')       { $_extraLabel = __('Oficina: ', 'signatures'); $_extraPhone = $_uPhone2; }
elseif ($_uPhone !== '')    { $_extraLabel = __('Ext: ',    'signatures'); $_extraPhone = $_uPhone; }
if (empty($_extraPhone))    { $_extraLabel = __('Ext: ', 'signatures'); $_extraPhone = '123'; }

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

   echo "<p class='text-muted small mb-2 sig-editor-hint'>
      <i class='ti ti-hand-move me-1'></i>"
      . __('Arrastra cada campo a su posición. Usa los inputs de tamaño para ajustar el tamaño de fuente. Guarda con el botón Guardar.', 'signatures') .
   "</p>";

   // Contenedor del editor — la imagen se escala con max-width:100%
   // Los campos se posicionan en coordenadas GD (espacio natural) y el JS
   // los reescala al tamaño visible en cuanto el tab se abre (#2)
   $cacheBustEditor = $hasTemplate
      ? filemtime(($baseId === 'b1')
         ? PluginSignaturesPaths::base1Path()
         : PluginSignaturesPaths::base2Path())
      : 0;
   echo "<div class='sig-editor-wrap' id='editor-{$baseId}' style='position:relative;display:inline-block;overflow:visible;'>";
   echo "<img src='{$bgUrl}&t={$cacheBustEditor}'
              id='img-{$baseId}'
              class='sig-bg'
              style='display:block;max-width:100%;pointer-events:none;'
              draggable='false'>";

   foreach ($fields as [$fieldId, $label, $gdX, $gdY, $gdSize, $fontType, $color, $sample]) {
      $isQr      = ($fieldId === 'qr');
      $fontCss   = $fontType === 'black' ? 'AvenirBlack' : 'AvenirRoman';
      $textColor = $color === 'white' ? '#fff' : '#000';

      // Coordenadas GD se usan también como CSS iniciales (el JS las escala al abrir el tab)
      $cssTop      = $isQr ? $gdY : max(0, (int)round($gdY - $gdSize * $ASCENT_FACTOR));
      $cssLeft     = $gdX;
      $cssFontSize = $gdSize;

      if ($isQr) {
         echo "<div class='sig-field sig-field-qr'
                    id='field-{$baseId}-{$fieldId}'
                    data-base='{$baseId}'
                    data-field='{$fieldId}'
                    data-is-qr='1'
                    data-gd-x='{$gdX}'
                    data-gd-y='{$gdY}'
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
                    data-gd-x='{$gdX}'
                    data-gd-y='{$gdY}'
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
   echo "<table class='table table-sm table-bordered sig-pos-table' style='max-width:420px'>";
   echo "<thead><tr>
      <th>" . __('Campo', 'signatures') . "</th>
      <th style='width:100px'>" . __('Tamaño (px)', 'signatures') . "</th>
      <th style='width:140px'>" . __('Posición X / Y', 'signatures') . "</th>
   </tr></thead><tbody>";

   foreach ($fields as [$fieldId, $label, $gdX, $gdY, $gdSize]) {
      $inputBase = "sig_{$baseId}_{$fieldId}";
      if ($fieldId === 'qr') {
         // #4: QR aparece en la tabla con su posición X,Y pero sin input de tamaño de fuente
      echo "<tr>
            <td><small>{$label}</small></td>
            <td><small class='text-muted'>—</small></td>
            <td>
               <div class='d-flex gap-1 align-items-center'>
                  <input type='number' min='0'
                         class='form-control form-control-sm sig-pos-input sig-pos-input-x'
                         data-base='{$baseId}' data-field='{$fieldId}'
                         id='pos-{$baseId}-{$fieldId}-x'
                         placeholder='X'
                         value='{$gdX}' style='width:60px;'>
                  <input type='number' min='0'
                         class='form-control form-control-sm sig-pos-input sig-pos-input-y'
                         data-base='{$baseId}' data-field='{$fieldId}'
                         id='pos-{$baseId}-{$fieldId}-y'
                         placeholder='Y'
                         value='{$gdY}' style='width:60px;'>
               </div>
            </td>
         </tr>";
         continue;
      }
      echo "<tr>
         <td><small>{$label}</small></td>
         <td>
            <input type='number' min='6' max='80'
                   class='form-control form-control-sm sig-size-input'
                   data-base='{$baseId}' data-field='{$fieldId}'
                   value='{$gdSize}' style='width:70px;'>
         </td>
         <td>
            <div class='d-flex gap-1 align-items-center'>
               <input type='number' min='0'
                      class='form-control form-control-sm sig-pos-input sig-pos-input-x'
                      data-base='{$baseId}' data-field='{$fieldId}'
                      id='pos-{$baseId}-{$fieldId}-x'
                      placeholder='X'
                      value='{$gdX}' style='width:60px;'>
               <input type='number' min='0'
                      class='form-control form-control-sm sig-pos-input sig-pos-input-y'
                      data-base='{$baseId}' data-field='{$fieldId}'
                      id='pos-{$baseId}-{$fieldId}-y'
                      placeholder='Y'
                      value='{$gdY}' style='width:60px;'>
            </div>
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
   __('Plantilla con celular — Editor de posiciones', 'signatures'),
   $_base1Url,
   $_fieldsB1,
   $hasbase1
);

$_renderEditor('b2',
   __('Plantilla sin celular — Editor de posiciones', 'signatures'),
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
echo "<button type='submit' name='save' id='btn-save-config' class='btn btn-warning'>"
   . "<i class='ti ti-device-floppy me-1' id='icon-save-config'></i>"
   . __('Guardar', 'signatures')
   . "</button>";
echo "</div>";

echo "</div>"; // fin card exterior
echo "</form>"; // cierre del form principal

// Form oculto del correo de prueba — FUERA del form principal (evita anidamiento HTML inválido)
// El <button form='sig-test-mail-form'> declarado dentro del tab-general se asocia a este form via HTML5.
echo "<form id='sig-test-mail-form' method='post' action='" . htmlspecialchars($_testUrl, ENT_QUOTES, 'UTF-8') . "' style='display:none;'>
   <input type='hidden' name='_glpi_csrf_token' value='{$_testCsrfToken}'>
   <input type='hidden' name='is_test' value='1'>
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

// Strings traducidos para JS — json_encode NO funciona dentro de heredoc
$_i18n_confirmReset   = json_encode(__('¿Restaurar todas las posiciones a los valores por defecto? Esta acción no se puede deshacer hasta guardar.', 'signatures'));
$_i18n_unsavedChanges = json_encode(__('Cambios sin guardar', 'signatures'));
$_i18n_onlyPng           = json_encode(__('Solo PNG permitido', 'signatures'));
$_i18n_formatPlaceholder = json_encode(__('texto', 'signatures'));

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
#btn-save-config, #btn-save-config:hover { color: #000 !important; }
.sig-editor-wrap { cursor: default; }
.sig-var-badge:hover { background:#b8d8ff !important; outline:1px solid #6ab0ff; }
.sig-format-toolbar .sig-fmt-btn { padding:1px 7px; font-size:0.82em; line-height:1.4; }

/* ── Dark-mode text fixes ─────────────────────────────────────────────
   Bootstrap text-muted / form-text usan --bs-secondary-color que
   en algunos temas GLPI queda muy bajo en contraste.
   Usamos color: inherit con opacidad para adaptarse a cualquier tema. */
.form-text {
   color: var(--bs-secondary-color, var(--bs-body-color));
   opacity: 0.80;
}
.sig-tab-nav .nav-link {
   color: var(--bs-nav-tabs-link-color, var(--bs-body-color));
}
.sig-tab-nav .nav-link.active {
   color: var(--bs-nav-tabs-link-active-color, var(--bs-body-color));
   font-weight: 600;
}
/* Tabla del editor: labels de campo y guiones de QR */
.sig-pos-table td,
.sig-pos-table th,
.sig-pos-table small {
   color: var(--bs-body-color);
}
/* Hint de ayuda dentro del editor */
.sig-editor-hint {
   color: var(--bs-secondary-color, var(--bs-body-color));
   opacity: 0.80;
}
</style>
<script>
const ASCENT      = 0.72;
const SIG_DEFAULTS = {$_defaults_js};
const SIG_I18N = {
   confirmReset:      {$_i18n_confirmReset},
   unsavedChanges:    {$_i18n_unsavedChanges},
   onlyPng:           {$_i18n_onlyPng},
   formatPlaceholder: {$_i18n_formatPlaceholder}
};

// ── #2 Escala: convierte coordenadas GD ↔ CSS según tamaño visible ─────
// La imagen base tiene ~650px de ancho natural pero se muestra más pequeña.
// Las dimensiones recomendadas de plantilla son 650×250 px.
// Todos los campos almacenan sus coords en espacio GD (naturalWidth).
// Al abrir el tab, applyScale() los reposiciona al tamaño CSS real.
function getScale(wrap) {
   const img = wrap && wrap.querySelector('img');
   if (!img || !img.naturalWidth) return 1;
   const s = img.clientWidth / img.naturalWidth;
   return s > 0 ? s : 1;
}

function applyScale(baseId) {
   const wrap = document.getElementById('editor-' + baseId);
   if (!wrap) return;
   const scale = getScale(wrap);
   wrap.dataset.scale = scale;

   const imgEl = wrap.querySelector('img.sig-bg');
   wrap.querySelectorAll('.sig-field').forEach(el => {
      const gdX  = parseFloat(el.dataset.gdX ?? el.offsetLeft);
      const gdY  = parseFloat(el.dataset.gdY ?? el.offsetTop);
      const isQr = el.dataset.isQr === '1';
      const size = parseInt(el.dataset.fontSize || '11');

      el.style.left = (gdX * scale) + 'px';
      el.style.top  = (isQr
         ? gdY * scale
         : Math.max(0, (gdY - size * ASCENT) * scale)) + 'px';

      if (isQr) {
         const px = Math.round(100 * scale);
         el.style.width  = px + 'px';
         el.style.height = px + 'px';
         const icon = el.querySelector('i');
         if (icon) icon.style.fontSize = Math.round(px * 0.45) + 'px';
      } else {
         el.style.fontSize = (size * scale) + 'px';
      }

      // Guardar límites GD mientras el canvas es visible (clientWidth > 0)
      // para que el clamp en la tabla de posiciones funcione aunque el canvas esté oculto
      if (imgEl && imgEl.clientWidth > 0) {
         const maxCssX = Math.max(0, imgEl.clientWidth  - el.offsetWidth);
         const maxCssY = Math.max(0, imgEl.clientHeight - el.offsetHeight);
         el.dataset.maxGdX = Math.round(maxCssX / scale);
         el.dataset.maxGdY = Math.round(maxCssY / scale) + (isQr ? 0 : Math.round(size * ASCENT));
      }
   });
}

// Inicializar cuando el tab de posiciones se muestra
// (antes es invisible y clientWidth = 0)
document.addEventListener('DOMContentLoaded', function () {
   const tabBtn = document.getElementById('btn-tab-positions');
   if (tabBtn) {
      tabBtn.addEventListener('shown.bs.tab', function () {
         ['b1', 'b2'].forEach(id => {
            const wrap = document.getElementById('editor-' + id);
            const img  = wrap && wrap.querySelector('img');
            if (!img) return;
            if (img.complete && img.naturalWidth > 0) {
               applyScale(id);
            } else {
               img.addEventListener('load', () => applyScale(id), { once: true });
            }
         });
      });
   }
   // Si el tab ya es visible al cargar (hash en URL)
   const pane = document.getElementById('tab-positions');
   if (pane && pane.classList.contains('show')) {
      ['b1', 'b2'].forEach(id => applyScale(id));
   }

   // Fix 7: activar tab desde hash de URL al cargar (tras redirect post-save)
   const hash = window.location.hash;
   if (hash) {
      const tabId = hash.replace('#', '');
      const targetPane = document.getElementById(tabId);
      if (targetPane) {
         // Encontrar el botón del tab correspondiente y activarlo
         const tabTrigger = document.querySelector('[data-bs-target="#' + tabId + '"]');
         if (tabTrigger && window.bootstrap) {
            new bootstrap.Tab(tabTrigger).show();
         }
      }
   }

   // Fix 7: sincronizar hidden active_tab al cambiar de tab
   document.querySelectorAll('[data-bs-toggle="tab"]').forEach(btn => {
      btn.addEventListener('shown.bs.tab', function () {
         const target = (this.dataset.bsTarget || '').replace('#tab-', '');
         const inp = document.getElementById('active_tab_input');
         if (inp && target) inp.value = target;
      });
   });
});

window.addEventListener('resize', () => {
   ['b1', 'b2'].forEach(id => applyScale(id));
});

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
      dot.title = SIG_I18N.unsavedChanges;
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

// ── #17 Spinner en botón Guardar ───────────────────────────────────────
document.querySelector('form[method="post"]')?.addEventListener('submit', function () {
   clearPositionsDirty();
   const btn  = document.getElementById('btn-save-config');
   const icon = document.getElementById('icon-save-config');
   if (btn)  btn.disabled = true;
   if (icon) icon.className = 'spinner-border spinner-border-sm me-1';
});

// ── #2 syncInputs: CSS → GD dividiendo por escala ──────────────────────
function syncInputs(el, cssLeft, cssTop) {
   const wrap  = el.closest('.sig-editor-wrap');
   const scale = parseFloat(wrap?.dataset.scale || '1') || 1;
   const base  = el.dataset.base;
   const field = el.dataset.field;
   const isQr  = el.dataset.isQr === '1';
   const size  = isQr ? 0 : parseInt(el.dataset.fontSize || '11');

   const gdX = Math.round(cssLeft / scale);
   const gdY = isQr
      ? Math.round(cssTop / scale)
      : Math.round(cssTop / scale + size * ASCENT);

   // Actualizar data attributes para que applyScale() use los nuevos valores
   el.dataset.gdX = gdX;
   el.dataset.gdY = gdY;

   const inpX = document.getElementById('inp-' + base + '-' + field + '-x');
   const inpY = document.getElementById('inp-' + base + '-' + field + '-y');
   if (inpX) inpX.value = gdX;
   if (inpY) inpY.value = gdY;

   // Actualizar inputs editables de la tabla
   const posX = document.getElementById('pos-' + base + '-' + field + '-x');
   const posY = document.getElementById('pos-' + base + '-' + field + '-y');
   if (posX) posX.value = gdX;
   if (posY) posY.value = gdY;
}

// ── #10 Drag & drop con soporte mouse Y touch ──────────────────────────
(function() {
   let dragging = null, ox = 0, oy = 0, startL = 0, startT = 0;

   function getCoords(e) {
      const src = e.touches ? e.touches[0] : e;
      return { x: src.clientX, y: src.clientY };
   }

   function onStart(e) {
      const el = e.target.closest('.sig-field');
      if (!el) return;
      e.preventDefault();
      dragging = el;
      const c = getCoords(e);
      ox = c.x; oy = c.y;
      startL = el.offsetLeft; startT = el.offsetTop;
      el.style.cursor = 'grabbing';
      el.style.zIndex = 999;
   }

   function onMove(e) {
      if (!dragging) return;
      e.preventDefault();
      const c      = getCoords(e);
      const editor = dragging.closest('.sig-editor-wrap');
      const img    = editor ? editor.querySelector('img.sig-bg') : null;
      const maxL   = img ? Math.max(0, img.clientWidth  - dragging.offsetWidth)  : 9999;
      const maxT   = img ? Math.max(0, img.clientHeight - dragging.offsetHeight) : 9999;
      const newL   = Math.min(maxL, Math.max(0, startL + c.x - ox));
      const newT   = Math.min(maxT, Math.max(0, startT + c.y - oy));
      dragging.style.left = newL + 'px';
      dragging.style.top  = newT + 'px';
      syncInputs(dragging, newL, newT);
      markPositionsDirty();
   }

   function onEnd() {
      if (!dragging) return;
      dragging.style.cursor = 'grab';
      dragging.style.zIndex = '';
      dragging = null;
   }

   document.addEventListener('mousedown',  onStart);
   document.addEventListener('mousemove',  onMove);
   document.addEventListener('mouseup',    onEnd);
   document.addEventListener('touchstart', onStart, { passive: false });
   document.addEventListener('touchmove',  onMove,  { passive: false });
   document.addEventListener('touchend',   onEnd);
})();

// ── Cambio de tamaño desde input ───────────────────────────────────────
document.addEventListener('input', e => {
   const inp = e.target;
   if (!inp.classList.contains('sig-size-input')) return;
   const base  = inp.dataset.base;
   const field = inp.dataset.field;
   const size  = parseInt(inp.value) || 11;

   const el   = document.getElementById('field-' + base + '-' + field);
   if (!el) return;
   const wrap  = el.closest('.sig-editor-wrap');
   const scale = parseFloat(wrap?.dataset.scale || '1') || 1;

   el.dataset.fontSize = size;
   el.style.fontSize   = (size * scale) + 'px';

   const inpS = document.getElementById('inp-' + base + '-' + field + '-size');
   if (inpS) inpS.value = size;

   syncInputs(el, el.offsetLeft, el.offsetTop);
   markPositionsDirty();
});

// ── Edición manual de posición X/Y ────────────────────────────────────
document.addEventListener('input', e => {
   const inp = e.target;
   if (!inp.classList.contains('sig-pos-input')) return;
   const base  = inp.dataset.base;
   const field = inp.dataset.field;
   const isX   = inp.classList.contains('sig-pos-input-x');

   const el    = document.getElementById('field-' + base + '-' + field);
   if (!el) return;
   const editor = el.closest('.sig-editor-wrap');
   const scale  = parseFloat(editor?.dataset.scale || '1') || 1;
   const isQr   = el.dataset.isQr === '1';
   const size   = isQr ? 0 : parseInt(el.dataset.fontSize || '11');

   // Usar límites pre-calculados por applyScale() (guardados en dataset)
   // cuando el canvas está en un tab oculto (clientWidth = 0)
   const maxGdX = el.dataset.maxGdX !== undefined
      ? parseInt(el.dataset.maxGdX)
      : (() => {
           const img = editor ? editor.querySelector('img.sig-bg') : null;
           const maxCssX = img ? Math.max(0, img.clientWidth - el.offsetWidth) : 9999;
           return Math.round(maxCssX / scale);
        })();
   const maxGdY = el.dataset.maxGdY !== undefined
      ? parseInt(el.dataset.maxGdY)
      : (() => {
           const img = editor ? editor.querySelector('img.sig-bg') : null;
           const maxCssY = img ? Math.max(0, img.clientHeight - el.offsetHeight) : 9999;
           return Math.round(maxCssY / scale) + (isQr ? 0 : Math.round(size * ASCENT));
        })();

   // Leer los dos inputs de posición
   const posXInp = document.getElementById('pos-' + base + '-' + field + '-x');
   const posYInp = document.getElementById('pos-' + base + '-' + field + '-y');
   let gdX = parseInt(posXInp?.value) || 0;
   let gdY = parseInt(posYInp?.value) || 0;

   // Clampear contra límites reales (descontando tamaño del elemento)
   gdX = Math.min(maxGdX, Math.max(0, gdX));
   gdY = Math.min(maxGdY, Math.max(0, gdY));

   // Reflejar valor clampeado en el input que se editó
   if (isX && posXInp) posXInp.value = gdX;
   else if (!isX && posYInp) posYInp.value = gdY;

   // Actualizar hidden inputs
   const inpX = document.getElementById('inp-' + base + '-' + field + '-x');
   const inpY = document.getElementById('inp-' + base + '-' + field + '-y');
   if (inpX) inpX.value = gdX;
   if (inpY) inpY.value = gdY;

   // Actualizar data attributes del elemento
   el.dataset.gdX = gdX;
   el.dataset.gdY = gdY;

   // Mover el elemento en el canvas (GD → CSS)
   const cssL = gdX * scale;
   const cssT = isQr
      ? gdY * scale
      : Math.max(0, (gdY - size * ASCENT) * scale);
   el.style.left = cssL + 'px';
   el.style.top  = cssT + 'px';

   markPositionsDirty();
});

// ── Reset a defaults ────────────────────────────────────────────────────
document.addEventListener('click', e => {
   const btn = e.target.closest('.sig-reset-btn');
   if (!btn) return;

   // Fix 4: confirmación antes de resetear
   if (!confirm(SIG_I18N.confirmReset)) return;

   const base  = btn.dataset.base;
   const defs  = SIG_DEFAULTS[base];
   if (!defs) return;

   const wrap  = document.getElementById('editor-' + base);
   const scale = parseFloat(wrap?.dataset.scale || '1') || 1;

   Object.entries(defs).forEach(([field, coords]) => {
      const el = document.getElementById('field-' + base + '-' + field);
      if (!el) return;
      const isQr = el.dataset.isQr === '1';
      const size = coords.size || 11;

      // Actualizar data GD
      el.dataset.gdX = coords.x;
      el.dataset.gdY = coords.y;

      const cssL = coords.x * scale;
      const cssT = isQr
         ? coords.y * scale
         : Math.max(0, (coords.y - size * ASCENT) * scale);

      el.style.left = cssL + 'px';
      el.style.top  = cssT + 'px';

      if (isQr) {
         const px = Math.round(100 * scale);
         el.style.width  = px + 'px';
         el.style.height = px + 'px';
         const icon = el.querySelector('i');
         if (icon) icon.style.fontSize = Math.round(px * 0.45) + 'px';
      } else {
         el.dataset.fontSize = size;
         el.style.fontSize   = (size * scale) + 'px';
         const inpS = document.getElementById('inp-' + base + '-' + field + '-size');
         if (inpS) inpS.value = size;
         const sizeInput = document.querySelector('.sig-size-input[data-base="' + base + '"][data-field="' + field + '"]');
         if (sizeInput) sizeInput.value = size;
      }
      syncInputs(el, cssL, cssT);
   });
   markPositionsDirty();
});


// ── Botones de formato B / I / U ──────────────────────────────────────
document.addEventListener('click', e => {
   const btn = e.target.closest('.sig-fmt-btn');
   if (!btn) return;
   const wrap = btn.dataset.wrap;
   const wLen = wrap.length;
   // Campo activo: textarea con foco, o el último que tuvo foco
   const ta = document.activeElement && document.activeElement.tagName === 'TEXTAREA'
      ? document.activeElement
      : window._sigLastTextarea;
   if (!ta) return;
   ta.focus();

   const start  = ta.selectionStart;
   const end    = ta.selectionEnd;
   const sel    = ta.value.substring(start, end);
   const before = ta.value.substring(start - wLen, start);
   const after  = ta.value.substring(end, end + wLen);

   // Toggle: quitar marcadores si ya existen dentro O fuera de la selección
   const wrappedInside  = sel.startsWith(wrap) && sel.endsWith(wrap) && sel.length >= wLen * 2 + 1;
   const wrappedOutside = before === wrap && after === wrap;

   let newStart, newEnd;
   if (wrappedInside) {
      // Quitar marcadores que están DENTRO de la selección
      const inner = sel.slice(wLen, sel.length - wLen);
      ta.setRangeText(inner, start, end, 'preserve');
      newStart = start;
      newEnd   = start + inner.length;
   } else if (wrappedOutside) {
      // Quitar marcadores que están FUERA de la selección
      ta.setRangeText(sel, start - wLen, end + wLen, 'preserve');
      newStart = start - wLen;
      newEnd   = newStart + sel.length;
   } else {
      // Aplicar: envolver selección (o placeholder si no hay selección)
      const inner = sel || SIG_I18N.formatPlaceholder;
      ta.setRangeText(wrap + inner + wrap, start, end, 'preserve');
      newStart = start + wLen;
      newEnd   = newStart + inner.length;
   }

   ta.setSelectionRange(newStart, newEnd);
   markPositionsDirty && markPositionsDirty();
});

// Recordar último textarea con foco
document.querySelectorAll('textarea[name="email_body"], textarea[name="email_footer"]')
   .forEach(ta => ta.addEventListener('focus', () => { window._sigLastTextarea = ta; }));


// ── Preview de plantillas (tabs Con/Sin celular) ──────────────────────
function preview(input, imgId, wrapId) {
   const file = input.files[0];
   if (!file) return;
   if (file.type !== 'image/png') {
      alert(SIG_I18N.onlyPng);
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
