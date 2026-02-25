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

   Session::addMessageAfterRedirect(__('Plantillas guardadas correctamente', 'signatures'), false, INFO);
   Html::redirect($self);
}

$config = Config::getConfigurationValues('plugin_signatures');
$facebookPage = $config['facebook_page']  ?? '';
$countryCode = $config['whatsapp_country_code']  ?? '';

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
echo "<div class='card mt-2 rounded-0'>";
signaturesRibbonSubHeader('ti-settings', 'Configuración general');
echo "<div class='card-body'>";

/* ================= FACEBOOK ================= */

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

/* ================= FIN FACEBOOK ================= */
/* ================= CODIGO PAIS WP ================= */
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
/* ================= FINNCODIGO PAIS WP ================= */

echo "</div></div></div>";

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

/* FOOTER */
echo "<div class='card-footer text-end'>
        <button type='submit' name='save' class='btn btn-primary'>
          <i class='ti ti-device-floppy'></i> " . __('Guardar', 'signatures') . "
        </button>
      </div>";

echo "</div>";
echo "</form>";

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