<?php
declare(strict_types=1);

class PluginSignaturesSignature {

   /**
    * Validación de prerequisitos generales
    */
   public static function checkRequirements(): array {

      $errors = [];

      // Imagen base
      $base = GLPI_PLUGIN_DOC_DIR . '/signatures/base.png';
      if (!is_readable($base)) {
         $errors[] = __('Base template image not found', 'signatures');
      }

      // Fuente TTF
      $font = self::getFontAvenirBlack();
      if (!is_readable($font)) {
         $errors[] = sprintf(
            __('TTF font not found for signatures: %s', 'signatures'),
            $font
         );
      }

      // GD
      if (!extension_loaded('gd')) {
         $errors[] = __('PHP GD extension is required', 'signatures');
      }

      return $errors;
   }

   /**
    * Ruta de la fuente TTF del plugin
    */
   private static function getFontAvenirBlack(): string {
      return GLPI_PLUGIN_DOC_DIR . '/signatures/fonts/AvenirBlack.ttf';
   }

   private static function getFontAvenirBook(): string {
      return GLPI_PLUGIN_DOC_DIR . '/signatures/fonts/AvenirBook.ttf';
   }
   
      private static function getFontAvenirRoman(): string {
      return GLPI_PLUGIN_DOC_DIR . '/signatures/fonts/AvenirRoman.ttf';
   }

   /**
    * Genera la firma en PNG
    */
   public static function generatePNG(User $user, bool $include_qr): string {

      /* ============================
       * VALIDACIÓN CELULAR (OBLIGATORIO)
       * ============================ */
      $mobile = trim((string)($user->fields['mobile'] ?? ''));

      if ($include_qr && $mobile === '') {
         throw new RuntimeException(
            __('User does not have a mobile phone number configured', 'signatures')
         );
      }

      /* ============================
       * IMAGEN BASE
       * ============================ */
      $basefile = GLPI_PLUGIN_DOC_DIR . '/signatures/base.png';
      $img = imagecreatefrompng($basefile);

      if (!$img) {
          echo $basefile;
         throw new RuntimeException('Cannot load base image');
      }

      imagesavealpha($img, true);
      imagealphablending($img, true);

      $black = imagecolorallocate($img, 0, 0, 0);
      $white = imagecolorallocate($img, 255, 255, 255);
      $fontblack  = self::getFontAvenirBlack();
      $fontbook  = self::getFontAvenirBook();
      $fontroman  = self::getFontAvenirRoman();

      /* ============================
       * ENTIDAD
       * ============================ */
      $entity = new Entity();
      $entity->getFromDB((int)$user->fields['entities_id']);

      /* ============================
       * DATOS USUARIO
       * ============================ */
      $name   = mb_convert_encoding($user->getFriendlyName(), 'UTF-8', 'auto');
      $titulo = 'No especificado';
      $email  = 'No especificado';
      $phone  = (string)($user->fields['phone'] ?? '');

      /* ============================
       * DATOS ENTIDAD
       * ============================ */
      $phone_entity = (string)$entity->getField('phonenumber');
      $web          = (string)$entity->getField('website');

      /* ============================
       * TÍTULO / CARGO
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
        $maxWidth = imagesx($img) - $startX - 20; // margen derecho
        
        $size = 55; // tamaño inicial
        
        while ($size > 20) {
        
           $bbox = imagettfbbox($size, 0, $fontblack, $name);
           $text_width = $bbox[2] - $bbox[0];
        
           if ($text_width <= $maxWidth) {
              break;
           }
        
           $size--;
        }

      /* ============================
       * UTF-8
       * ============================ */
      $titulo = mb_convert_encoding($titulo, 'UTF-8', 'auto');
      $email  = mb_convert_encoding($email, 'UTF-8', 'auto');

      /* ============================
       * TEXTO SOBRE IMAGEN
       * ============================ */
      imagettftext($img, $size, 0, $startX, 75, $white, $fontblack, $name);
      imagettftext($img, 15, 0, 20, 113, $white, $fontroman, $titulo); // titulo usuario
      imagettftext($img, 12, 0, 70, 149, $black, $fontroman, $email);  // email usuario
      imagettftext($img, 12, 0, 70, 173, $black, $fontroman, $mobile);        // móvil
      imagettftext($img, 12, 0, 190, 173, $black, $fontroman, $phone_entity);  // tel entidad
        if (!empty(trim($phone))) {
           imagettftext($img, 12, 0, 290, 173, $black, $fontroman, 'Ext: ' . $phone); // ext usuaario
        }
      imagettftext($img, 12, 0, 70, 198, $black, $fontroman, 'cyalimentos');           // wweb facebook
      imagettftext($img, 12, 0, 190, 198, $black, $fontroman, $web);           // web entidad

      /* ============================
       * QR — WhatsApp Web
       * ============================ */
      if ($include_qr) {

         require_once GLPI_ROOT . '/vendor/tecnickcom/tcpdf/tcpdf_barcodes_2d.php';

         // Limpia el número (solo dígitos)
         $mobile_clean = preg_replace('/\D+/', '', $mobile);

         // URL WhatsApp Web
         $wa_url = 'https://wa.me/52' . $mobile_clean;

         $barcode = new TCPDF2DBarcode($wa_url, 'QRCODE,M');

         $qr_png = $barcode->getBarcodePngData(4, 4, [0, 0, 0]);

         $qr_tmp = GLPI_TMP_DIR . '/signature_qr_' . $user->getID() . '.png';
         file_put_contents($qr_tmp, $qr_png);

         if (is_readable($qr_tmp)) {
            $qr = imagecreatefrompng($qr_tmp);
            imagecopy($img, $qr, 590, 125, 0, 0, 100, 100);
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