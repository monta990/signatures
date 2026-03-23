<?php
declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
   die('Direct access not allowed');
}

class PluginSignaturesUser extends CommonGLPI {

   /**
    * Nombre del tipo
    */
   public static function getTypeName($nb = 0): string {
      return __('Email Signatures', 'signatures');
   }

   /* =====================================================
    * TAB (GLPI 11 → SIEMPRE ARRAY)
    * ===================================================== */
   public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): array {

      if (!$item instanceof User) {
         return [];
      }

      // #11: indicador visual si la configuración está incompleta
      $hasMobile = !empty($item->fields['mobile']);
      $hasBase   = $hasMobile
         ? is_readable(PluginSignaturesPaths::base1Path())
         : is_readable(PluginSignaturesPaths::base2Path());
      $hasEmail  = empty(PluginSignaturesSignature::checkEmailConfig());

      $badge = '';
      if (!$hasBase || !$hasEmail) {
         $badge = " <span class='badge bg-warning text-dark ms-1' style='font-size:0.65em;' "
                . "title='" . htmlspecialchars(__('Incomplete configuration', 'signatures'), ENT_QUOTES, 'UTF-8') . "'>!</span>";
      }

      return [
         1 => "<span class='d-flex align-items-center'>
                             <i class='ti ti-mail me-2'></i>" .
                             __('Email Signature', 'signatures') .
                             $badge .
                          "</span>"
      ];
   }

   /* =====================================================
    * CONTENIDO DEL TAB
    * ===================================================== */
   public static function displayTabContentForItem(
      CommonGLPI $item,
      $tabnum = 1,
      $withtemplate = 0
   ): bool {

      if ($item instanceof User) {
         self::showTab($item);
      }

      return true;
   }

   /* =====================================================
    * UI DEL TAB
    * ===================================================== */
   private static function showTab(User $user): void {

      /* ===========================
       * Validar ambas plantillas
       * =========================== */
      $hasMobile = !empty($user->fields['mobile']);

      // Solo se necesita la plantilla correspondiente al usuario
      $hasBase = $hasMobile
         ? is_readable(PluginSignaturesPaths::base1Path())
         : is_readable(PluginSignaturesPaths::base2Path());

      /* ===========================
       * Validar configuración de correo
       * =========================== */
      $emailErrors = PluginSignaturesSignature::checkEmailConfig();
      $hasEmail    = empty($emailErrors);

      $downloadUrl = Plugin::getWebDir('signatures') . '/front/download.php';
      $sendUrl     = Plugin::getWebDir('signatures') . '/front/send.php';

      // Mensajes GLPI (redirect-safe)
      Html::displayMessageAfterRedirect();

      /* ===========================
       * Header
       * =========================== */
      echo "<div class='card-header pt-2 position-relative'>
             <div class='ribbon ribbon-bookmark ribbon-top ribbon-start bg-blue s-1'>
                <i class='fs-2x ti ti-mail'></i>
             </div>
             <h4 class='card-title ms-5 mb-0'>" .
                __('Generate email signature', 'signatures') .
             "</h4>
          </div>";

      echo "<div class='card-body text-center'>";

      /* ===========================
       * Aviso: plantillas faltantes
       * =========================== */
      if (!$hasBase) {
         echo "<div class='alert alert-warning text-start'>
                <i class='ti ti-alert-triangle me-2'></i>
                <strong>" . __('Templates not found.', 'signatures') . "</strong><br>
                " . __('Please verify that the templates exist in the plugin configuration.', 'signatures') . "
             </div>";
      }

      /* ===========================
       * Aviso: correo sin configurar
       * =========================== */
      if (!$hasEmail) {
         echo "<div class='alert alert-info text-start'>
                <i class='ti ti-mail-off me-2'></i>
                <strong>" . __('Email sending is not available.', 'signatures') . "</strong><br>
                " . __('Configure the email subject and body in the plugin settings to enable it.', 'signatures') . "
             </div>";
      }

      echo "<p class='text-body-secondary mb-3'>" .
               sprintf(
                  __('Generating signature for: %s', 'signatures'),
                  $user->getFriendlyName()
               ) .
           "</p>";

      /* ===========================
       * Checkbox QR (compartido)
       * =========================== */
      if ($hasMobile) {
         echo "<div class='mb-3'>
                  <label class='form-check d-inline-flex align-items-center gap-2'>
                     <input type='checkbox'
                            class='form-check-input'
                            id='qr_check'
                            value='1'
                            checked>
                     " . __('Include WhatsApp QR code', 'signatures') . "
                  <i class='ti ti-brand-whatsapp ms-1'></i></label>
               </div>";
      } else {
         echo "<p class='text-body-secondary'><i class='ti ti-info-circle me-1'></i>" .
              __('This user has no mobile number; the QR code will not be available.', 'signatures') .
              "</p>";
      }

      /* ===========================
       * Botones: Descargar | Enviar
       * =========================== */
      echo "<div class='d-flex gap-3 justify-content-center mt-4'>";

      /* --- Botón Descargar firma --- */
      echo "<form method='get' action='{$downloadUrl}' id='form-download'>
               <input type='hidden' name='userid'     value='{$user->getID()}'>
               <input type='hidden' name='include_qr' id='qr_download' value='" . ($hasMobile ? '1' : '') . "'>
               <button type='submit'
                       class='btn btn-primary'
                       " . (!$hasBase ? 'disabled' : '') . ">
                  <i class='ti ti-download me-2'></i>
                  " . __('Download signature', 'signatures') . "
               </button>
            </form>";

      /* --- Botón Vista Previa --- */
      echo "<button type='button'
                     class='btn btn-outline-secondary'
                     id='btn-preview-sig'
                     data-userid='{$user->getID()}'
                     data-qrval='" . ($hasMobile ? '1' : '') . "'
                     " . (!$hasBase ? 'disabled' : '') . ">
               <i class='ti ti-eye me-2'></i>" .
               __('Preview', 'signatures') .
            "</button>";

      /* --- Botón Enviar por correo --- */
      echo "<form method='post' action='{$sendUrl}' id='form-send'>
               " . Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]) . "
               <input type='hidden' name='userid'     value='{$user->getID()}'>
               <input type='hidden' name='include_qr' id='qr_send' value='" . ($hasMobile ? '1' : '') . "'>
               <button type='submit'
                       class='btn btn-primary'
                       " . (!$hasBase || !$hasEmail ? 'disabled' : '') . ">
                  <i class='ti ti-send me-2'></i>
                  " . __('Send by email', 'signatures') . "
               </button>
            </form>";

      echo "</div>"; // fin botones

      echo "</div></div>";

      /* ===========================
       * Modal Vista Previa
       * =========================== */
      $downloadUrlEsc = htmlspecialchars($downloadUrl, ENT_QUOTES, 'UTF-8');
      echo "<div class='modal fade' id='sig-preview-modal' tabindex='-1' aria-hidden='true'>
         <div class='modal-dialog modal-xl modal-dialog-centered'>
            <div class='modal-content'>
               <div class='modal-header py-2'>
                  <h6 class='modal-title'>
                     <i class='ti ti-eye me-2'></i>" .
                     __('Signature preview', 'signatures') .
                  "</h6>
                  <button type='button' class='btn-close' data-bs-dismiss='modal'></button>
               </div>
               <div class='modal-body text-center py-4'>
                  <div id='sig-preview-loading' style='display:none;'>
                     <span class='spinner-border spinner-border-sm me-2'></span>" .
                     __('Generating preview...', 'signatures') .
                  "</div>
                  <img id='sig-preview-img' src='' alt='signature preview'
                       style='max-width:100%;display:none;border:1px solid #dee2e6;'>
               </div>
            </div>
         </div>
      </div>";

      /* ===========================
       * JS: checkbox + preview + feedback botones
       * =========================== */
      $_i18n_previewError = json_encode(__('Error loading preview', 'signatures'));
      echo <<<HTML
<script>
const SIG_USER_I18N = {
   previewError: {$_i18n_previewError}
};
// Sincronizar checkbox QR con ambos forms
(function() {
   const chk = document.getElementById('qr_check');
   if (!chk) return;
   chk.addEventListener('change', function () {
      const val = this.checked ? '1' : '';
      const dl = document.getElementById('qr_download');
      const sn = document.getElementById('qr_send');
      if (dl) dl.value = val;
      if (sn) sn.value = val;
   });
})();

// Feedback visual en botones al hacer submit
// Descarga (GET): la página NO recarga — usar cookie polling para restaurar el botón
// Envío (POST): la página recarga sola al volver del redirect
(function() {
   const COOKIE = 'sig_download_done';

   function setCookie(name) {
      document.cookie = name + '=1; path=/; SameSite=Strict';
   }
   function clearCookie(name) {
      document.cookie = name + '=; path=/; max-age=0; SameSite=Strict';
   }
   function hasCookie(name) {
      return document.cookie.split(';').some(c => c.trim().startsWith(name + '='));
   }
   function restoreBtn(btn, origIcon) {
      btn.disabled = false;
      const icon = btn.querySelector('i');
      if (icon) icon.className = origIcon;
   }

   // Botón de descarga — GET, página no recarga
   const dlForm = document.getElementById('form-download');
   if (dlForm) {
      dlForm.addEventListener('submit', function () {
         const btn = this.querySelector('button[type="submit"]');
         if (!btn || btn.disabled) return;
         const origIcon = btn.querySelector('i')?.className || 'ti ti-download me-2';
         btn.disabled = true;
         const icon = btn.querySelector('i');
         if (icon) icon.className = 'spinner-border spinner-border-sm me-2';
         clearCookie(COOKIE);
         // Poll hasta que download.php setee la cookie (máx 30 s)
         let elapsed = 0;
         const poll = setInterval(() => {
            elapsed += 400;
            if (hasCookie(COOKIE) || elapsed > 30000) {
               clearInterval(poll);
               clearCookie(COOKIE);
               restoreBtn(btn, origIcon);
            }
         }, 400);
      });
   }

   // Botón de envío — POST, página recarga sola
   const snForm = document.getElementById('form-send');
   if (snForm) {
      snForm.addEventListener('submit', function () {
         const btn = this.querySelector('button[type="submit"]');
         if (!btn || btn.disabled) return;
         btn.disabled = true;
         const icon = btn.querySelector('i');
         if (icon) icon.className = 'spinner-border spinner-border-sm me-2';
      });
   }
})();

// Modal Vista Previa
(function() {
   const btn = document.getElementById('btn-preview-sig');
   if (!btn) return;
   btn.addEventListener('click', function () {
      const modalEl = document.getElementById('sig-preview-modal');
      const modal   = bootstrap.Modal.getOrCreateInstance(modalEl);
      const img     = document.getElementById('sig-preview-img');
      const loading = document.getElementById('sig-preview-loading');
      const qrCheck = document.getElementById('qr_check');
      const qrVal   = qrCheck ? (qrCheck.checked ? '1' : '') : this.dataset.qrval;
      const url     = '{$downloadUrlEsc}?userid=' + this.dataset.userid
                    + '&include_qr=' + qrVal + '&preview=1';

      img.style.display     = 'none';
      img.src               = '';
      loading.style.display = '';
      modal.show();

      img.onload = () => {
         loading.style.display = 'none';
         img.style.display     = '';
      };
      img.onerror = () => {
         loading.style.display = 'none';
         img.alt               = SIG_USER_I18N.previewError;
         img.style.display     = '';
      };
      img.src = url;
   });
})();
</script>
HTML;
   }
}
