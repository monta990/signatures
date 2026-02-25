<?php
declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
   die('Direct access not allowed');
}

class PluginSignaturesUser extends CommonGLPI {

   /**
    * Nombre del tipo
    */
   public static function getTypeName($nb = 0) {
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

      $base1 = PluginSignaturesPaths::base1Path(); // con celular
      $base2 = PluginSignaturesPaths::base2Path(); // sin celular

      $hasBase1 = is_readable($base1);
      $hasBase2 = is_readable($base2);

      // SOLO válido si existen ambas
      $hasBase = ($hasBase1 && $hasBase2);

      $downloadUrl = Plugin::getWebDir('signatures') . '/front/download.php';

      // Mensajes GLPI (redirect-safe)
      Html::displayMessageAfterRedirect();

      echo "<div class='card mt-3 shadow-sm'>";

      /* ===========================
       * Header
       * =========================== */
      echo "
      <div class='card-header pt-2 position-relative'>
         <div class='ribbon ribbon-bookmark ribbon-top ribbon-start bg-blue s-1'>
            <i class='fs-2x ti ti-mail'></i>
         </div>
         <h4 class='card-title ms-5 mb-0'>" .
            __('Generar firma para correo', 'signatures') .
         "</h4>
      </div>";

      echo "<div class='card-body text-center'>";

      /* ===========================
       * Validación plantillas
       * =========================== */
      if (!$hasBase) {
         echo "
         <div class='alert alert-warning text-start'>
            <i class='ti ti-alert-triangle me-2'></i>
            <strong>" . __('No se encontraron las plantillas.', 'signatures') . "</strong><br>
            " . __('Por favor valida que las plantillas existan en la configuración del complemento.', 'signatures') . "
         </div>";
      }

      echo "
         <p class='text-muted mb-3'>" .
            sprintf(
               __('Generando firma para: %s', 'signatures'),
               $user->getFriendlyName()
            ) .
         "</p>";

      /* ===========================
       * Formulario descarga
       * =========================== */
      echo "
      <form method='get' action='{$downloadUrl}'>
         <input type='hidden' name='userid' value='{$user->getID()}'>";

        $hasMobile = !empty($user->fields['mobile']);
        
        if ($hasMobile) {
          echo "
             <label class='form-check d-inline-flex align-items-center gap-2'>
                <input type='checkbox'
                       class='form-check-input'
                       name='include_qr'
                       value='1'
                       checked >
                " . __('Incluir (si el usuario tiene número celular) código QR para Whatsapp', 'signatures') . "
             <i class='ti ti-brand-whatsapp me-2'></i></label>";
        } else {
           echo "<p class='text-muted'><i class='ti ti-info-circle me-1'></i>" . 
                __('Este usuario no tiene número celular, el QR no estará disponible.', 'signatures') . 
                "</p>";
        }

      echo "
         <div class='mt-4'>
            <button type='submit'
                    class='btn btn-primary'
                    " . (!$hasBase ? 'disabled' : '') . ">
               <i class='ti ti-download me-2'></i>
               " . __('Descargar firma', 'signatures') . "
            </button>
         </div>
      </form>";

      echo "</div></div>";
   }
}