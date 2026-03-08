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
    echo "<h3 class='card-subtitle ms-5 mb-0'>" . __($title, 'signatures') . "</h3>";
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

   Session::addMessageAfterRedirect(__('Configuración guardada correctamente', 'signatures'), false, INFO);
   Html::redirect($self);
}

$config       = Config::getConfigurationValues('plugin_signatures');
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

echo "<div class='card mt-2 shadow-sm'>";

/* TITLE */
echo "<div class='card-header mb-3 py-1 border-top rounded-0 position-relative'>";
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

</ul>
";

echo "<div class='tab-content'>";

/* =====================================================
 * TAB GENERAL
 * ===================================================== */
echo "<div class='tab-pane fade show active' id='tab-general'>";

/* ================= SECCIÓN: CONFIGURACIÓN GENERAL ================= */
echo "<div class='card mt-2 rounded-0'>";
signaturesRibbonSubHeader('ti-settings', 'Configuración general');
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

echo "</div></div>";

/* ================= SECCIÓN: OPCIONES DE CORREO ================= */
echo "<div class='card mt-3 rounded-0'>";
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
      <table class='table table-borderless table-sm mb-0'>
        <tbody>
          <tr>
            <td><code style='background:#ddeeff;padding:2px 6px;border-radius:3px;'>{nombre}</code></td>
            <td class='text-muted'>— " . __('Nombre completo del usuario', 'signatures') . "</td>
          </tr>
          <tr>
            <td><code style='background:#ddeeff;padding:2px 6px;border-radius:3px;'>{empresa}</code></td>
            <td class='text-muted'>— " . __('Nombre de la empresa (configurado en General)', 'signatures') . "</td>
          </tr>
          <tr>
            <td><code style='background:#ddeeff;padding:2px 6px;border-radius:3px;'>{fecha}</code></td>
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

echo "</div></div>";

echo "</div>"; // fin tab-general

/* =====================================================
 * TAB CON CELULAR
 * ===================================================== */
echo "<div class='tab-pane fade' id='tab-cel'>";
echo "<div class='card mt-2 rounded-0'>";
signaturesRibbonSubHeader('ti-device-mobile', 'Plantilla con celular');
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

echo "</div></div></div>";

/* =====================================================
 * TAB SIN CELULAR
 * ===================================================== */
echo "<div class='tab-pane fade' id='tab-nocel'>";
echo "<div class='card mt-2 rounded-0'>";
signaturesRibbonSubHeader('ti-phone-off', 'Plantilla sin celular');
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

echo "</div></div></div>";

echo "</div>"; // tab-content

/* FOOTER — Guardar dentro del form principal */
echo "<div class='card-footer text-end'>";
echo "<button type='submit' name='save' class='btn btn-primary'>"
   . "<i class='ti ti-device-floppy me-1'></i>"
   . __('Guardar', 'signatures')
   . "</button>";
echo "</div>";

echo "</div>"; // fin .card
echo "</form>"; // cierre del form principal

// Form oculto del correo de prueba — FUERA del form principal (evita anidamiento HTML inválido)
// El <button form='sig-test-mail-form'> declarado dentro del tab-general se asocia a este form via HTML5.
echo "<form id='sig-test-mail-form' method='post' action='" . htmlspecialchars($_testUrl, ENT_QUOTES, 'UTF-8') . "' style='display:none;'>
   <input type='hidden' name='_glpi_csrf_token' value='{$_testCsrfToken}'>
</form>";

/* ========================== JS PREVIEW ========================== */
echo <<<HTML
<script>
function preview(input, imgId, wrapId) {
   const file = input.files[0];
   if (!file) return;

   if (file.type !== 'image/png') {
      alert('Solo PNG permitido');
      input.value='';
      return;
   }

   const reader = new FileReader();
   reader.onload = e => {
      document.getElementById(imgId).src = e.target.result;
      document.getElementById(wrapId).classList.remove('d-none');
   };
   reader.readAsDataURL(file);
}
</script>
HTML;

Html::footer();
