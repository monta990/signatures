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

      return [
         'signatures' => "<span class='d-flex align-items-center'>
                             <i class='ti ti-mail me-2'></i>" .
                             __('Firma de correo', 'signatures') .
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

      echo "<div class='card mt-3 shadow-sm'>";

      /* ===========================
       * Header
       * =========================== */
      echo "<div class='card-header pt-2 position-relative'>
             <div class='ribbon ribbon-bookmark ribbon-top ribbon-start bg-blue s-1'>
                <i class='fs-2x ti ti-mail'></i>
             </div>
             <h4 class='card-title ms-5 mb-0'>" .
                __('Generar firma para correo', 'signatures') .
             "</h4>
          </div>";

      echo "<div class='card-body text-center'>";

      /* ===========================
       * Aviso: plantillas faltantes
       * =========================== */
      if (!$hasBase) {
         echo "<div class='alert alert-warning text-start'>
                <i class='ti ti-alert-triangle me-2'></i>
                <strong>" . __('No se encontraron las plantillas.', 'signatures') . "</strong><br>
                " . __('Por favor valida que las plantillas existan en la configuración del complemento.', 'signatures') . "
             </div>";
      }

      /* ===========================
       * Aviso: correo sin configurar
       * =========================== */
      if (!$hasEmail) {
         echo "<div class='alert alert-info text-start'>
                <i class='ti ti-mail-off me-2'></i>
                <strong>" . __('El envío por correo no está disponible.', 'signatures') . "</strong><br>
                " . __('Configura el asunto y cuerpo del correo en la configuración del complemento para habilitarlo.', 'signatures') . "
             </div>";
      }

      echo "<p class='text-muted mb-3'>" .
               sprintf(
                  __('Generando firma para: %s', 'signatures'),
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
                     " . __('Incluir código QR de WhatsApp', 'signatures') . "
                  <i class='ti ti-brand-whatsapp ms-1'></i></label>
               </div>";
      } else {
         echo "<p class='text-muted'><i class='ti ti-info-circle me-1'></i>" .
              __('Este usuario no tiene número celular, el QR no estará disponible.', 'signatures') .
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
                  " . __('Descargar firma', 'signatures') . "
               </button>
            </form>";

      /* --- Botón Enviar por correo --- */
      echo "<form method='post' action='{$sendUrl}' id='form-send'>
               " . Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]) . "
               <input type='hidden' name='userid'     value='{$user->getID()}'>
               <input type='hidden' name='include_qr' id='qr_send' value='" . ($hasMobile ? '1' : '') . "'>
               <button type='submit'
                       class='btn btn-success'
                       " . (!$hasBase || !$hasEmail ? 'disabled' : '') . ">
                  <i class='ti ti-send me-2'></i>
                  " . __('Enviar por correo', 'signatures') . "
               </button>
            </form>";

      echo "</div>"; // fin botones

      echo "</div></div>";

      /* ===========================
       * JS: sincronizar checkbox con ambos forms
       * =========================== */
      if ($hasMobile) {
         echo <<<HTML
<script>
document.getElementById('qr_check').addEventListener('change', function () {
   const val = this.checked ? '1' : '';
   document.getElementById('qr_download').value = val;
   document.getElementById('qr_send').value     = val;
});
</script>
HTML;
      }
   }
}
