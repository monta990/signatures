<?php
declare(strict_types=1);

class PluginSignaturesSignature {

   public static function checkRequirements(bool $include_qr = false): array {

      $errors = [];
      
      $base1 = PluginSignaturesPaths::base1Path();
      $base2 = PluginSignaturesPaths::base2Path();

      if (!is_readable($base1) || !is_readable($base2)) {
         $errors[] = __('Las plantillas no están correctamente cargadas validalo en configuración del complemento', 'signatures');
      }

      $font = PluginSignaturesPaths::getFontAvenirBlack();
      if (!is_readable($font)) {
         $errors[] = sprintf(__('No se encontró la fuente TTF: %s', 'signatures'), $font);
      }
      
      $font2 = PluginSignaturesPaths::getFontAvenirRoman();
      if (!is_readable($font2)) {
         $errors[] = sprintf(__('No se encontró la fuente TTF: %s', 'signatures'), $font2);
      }

      if (!extension_loaded('gd')) {
         $errors[] = __('Extensión GD de PHP es requerida', 'signatures');
      }
      
       if ($include_qr) {
          $configsig = Config::getConfigurationValues('plugin_signatures');
          $countryCode = trim($configsig['whatsapp_country_code'] ?? '');
    
          if ($countryCode === '') {
             $errors[] = __('El código de país para Whatsapp no está configurado. Defínelo en la configuración del complemento.', 'signatures');
          }
       }

      return $errors;
   }

   /**
    * Genera la firma en PNG
    */
   public static function generatePNG(User $user, bool $include_qr): string {

      $configsig = Config::getConfigurationValues('plugin_signatures');
      $facebook = $configsig['facebook_page'] ?? '';

      /* ============================
       * CELULAR / EXT / OFICINA
       * ============================ */
      $mobile = trim((string)($user->fields['mobile'] ?? '')); //Celular usuario
      $phone  = trim((string)($user->fields['phone'] ?? ''));  //Como extensión de conmutador
      $phone2 = trim((string)($user->fields['phone2'] ?? ''));  //Si no hay extension es porque es una oficina remota y se usa este dato si esta poblado

      $hasMobile = ($mobile !== '');

      /* ============================
       * SELECCIÓN EXTRA (Ext / Oficina)
       * ============================ */
      $extraLabel = '';
      $extraPhone = '';

      if ($phone2 !== '') {
         $extraLabel = __('Oficina:', 'signatures') . ' ';
         $extraPhone = $phone2;
      } elseif ($phone !== '') {
         $extraLabel = __('Ext:', 'signatures') . ' ';
         $extraPhone = $phone;
      }

      /* ============================
       * IMAGEN BASE
       * ============================ */
      $basefile = $hasMobile
         ? PluginSignaturesPaths::base1Path()
         : PluginSignaturesPaths::base2Path();

      $img = imagecreatefrompng($basefile);

      if (!$img) {
         throw new RuntimeException(
            sprintf(__('No se pudo cargar la plantilla: %s', 'signatures'), $basefile)
         );
      }

      imagesavealpha($img, true);
      imagealphablending($img, true);

      $black = imagecolorallocate($img, 0, 0, 0);
      $white = imagecolorallocate($img, 255, 255, 255);

      $fontblack = PluginSignaturesPaths::getFontAvenirBlack();
      $fontroman = PluginSignaturesPaths::getFontAvenirRoman();

      /* ============================
       * ENTIDAD
       * ============================ */
      $entity = new Entity();
      if (!$entity->getFromDB((int)$user->fields['entities_id'])) {
         throw new RuntimeException(__('Entidad no válida', 'signatures'));
      }

      /* ============================
       * DATOS USUARIO
       * ============================ */
      $name = $user->getFriendlyName();
      $titulo = __('No especificado', 'signatures');
      $email  = __('No especificado', 'signatures');

      $phone_entity = (string)$entity->getField('phonenumber');
      $web          = (string)$entity->getField('website');

      /* ============================
       * TÍTULO
       * ============================ */
      if (!empty($user->fields['usertitles_id'])) {
         $titulo = Dropdown::getDropdownName(
            'glpi_usertitles',
            (int)$user->fields['usertitles_id']
         );
      }

      /* ============================
       * EMAIL PRINCIPAL
       * ============================ */
      $useremail = new UserEmail();
      $emails = $useremail->find([
         'users_id'   => (int)$user->getID(),
         'is_default' => 1
      ], [], 1);

      if (!empty($emails)) {
         $row = reset($emails);
         if (!empty($row['email'])) {
            $email = $row['email'];
         }
      }

      /* ============================
       * AJUSTE AUTOMÁTICO NOMBRE
       * ============================ */
      $startX = 20;
      $maxWidth = imagesx($img) - $startX - 20;
      $size = 40;

      while ($size > 20) {
         $bbox = imagettfbbox($size, 0, $fontblack, $name);
         $text_width = $bbox[2] - $bbox[0];
         if ($text_width <= $maxWidth) break;
         $size--;
      }

      /* ============================
       * TEXTO SOBRE IMAGEN
       * ============================ */
      imagettftext($img, $size, 0, $startX, 75, $white, $fontblack, $name);
      imagettftext($img, 11, 0, 20, 104, $white, $fontblack, $titulo);
      imagettftext($img, 11, 0, 63, 138, $black, $fontroman, $email);

      /* ============================
       * TELÉFONOS DINÁMICOS
       * ============================ */
      if ($hasMobile) {

         // Móvil
         imagettftext($img, 11, 0, 63, 161, $black, $fontroman, $mobile);

         // Tel entidad
         imagettftext($img, 11, 0, 185, 161, $black, $fontroman, $phone_entity);

         // Oficina o Ext
         if ($extraPhone !== '') {
            imagettftext($img, 11, 0, 283, 161, $black, $fontroman, $extraLabel . $extraPhone);
         }

      } else {

         // Sin móvil → solo entidad
         imagettftext($img, 11, 0, 63, 161, $black, $fontroman, $phone_entity);

         // Oficina o Ext
         if ($extraPhone !== '') {
            imagettftext($img, 11, 0, 160, 161, $black, $fontroman, $extraLabel . $extraPhone);
         }
      }

      imagettftext($img, 11, 0, 63, 183, $black, $fontroman, $facebook);
      imagettftext($img, 11, 0, 185, 183, $black, $fontroman, $web);

      /* ============================
       * QR SOLO SI HAY CELULAR
       * ============================ */
      if ($include_qr && $hasMobile) {

         require_once GLPI_ROOT . '/vendor/tecnickcom/tcpdf/tcpdf_barcodes_2d.php';

         $mobile_clean = preg_replace('/\D+/', '', $mobile);
         $wa_url = 'https://wa.me/' . trim($configsig['whatsapp_country_code'] ?? '') . $mobile_clean;

         $barcode = new TCPDF2DBarcode($wa_url, 'QRCODE,M');
         $qr_png = $barcode->getBarcodePngData(3, 3, [0, 0, 0]);

         $qr_tmp = GLPI_TMP_DIR . '/signature_qr_' . $user->getID() . '.png';
         file_put_contents($qr_tmp, $qr_png);

         if (is_readable($qr_tmp)) {
            $qr = imagecreatefrompng($qr_tmp);
            imagecopy($img, $qr, 560, 130, 0, 0, 100, 100);
            imagedestroy($qr);
            unlink($qr_tmp);
         }
      }

      /* ============================
       * SALIDA FINAL
       * ============================ */
      $out = GLPI_TMP_DIR . '/signature_' . $user->getID() . '.png';
      imagepng($img, $out);
      imagedestroy($img);

      return $out;
   }
}