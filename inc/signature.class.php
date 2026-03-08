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
         $configsig    = Config::getConfigurationValues('plugin_signatures');
         $countryCode  = trim($configsig['whatsapp_country_code'] ?? '');

         if ($countryCode === '') {
            $errors[] = __('El código de país para Whatsapp no está configurado. Defínelo en la configuración del complemento.', 'signatures');
         }
      }

      return $errors;
   }

   /**
    * Verifica que la configuración de correo esté completa (asunto y cuerpo obligatorios).
    * Retorna array de errores; vacío si todo está bien.
    */
   public static function checkEmailConfig(): array {

      $errors  = [];
      $config  = Config::getConfigurationValues('plugin_signatures');
      $subject = trim($config['email_subject'] ?? '');
      $body    = trim($config['email_body']    ?? '');

      if ($subject === '') {
         $errors[] = __('El asunto del correo no está configurado. Defínelo en la configuración del complemento.', 'signatures');
      }

      if ($body === '') {
         $errors[] = __('El cuerpo del correo no está configurado. Defínelo en la configuración del complemento.', 'signatures');
      }

      return $errors;
   }

   /**
    * Genera la firma en PNG y devuelve la ruta del archivo temporal.
    */
   public static function generatePNG(User $user, bool $include_qr): string {

      $configsig = Config::getConfigurationValues('plugin_signatures');
      $facebook  = $configsig['facebook_page'] ?? '';

      /* ============================
       * CELULAR / EXT / OFICINA
       * ============================ */
      $mobile = trim((string)($user->fields['mobile'] ?? '')); // Celular usuario
      $phone  = trim((string)($user->fields['phone']  ?? '')); // Como extensión de conmutador
      $phone2 = trim((string)($user->fields['phone2'] ?? '')); // Oficina remota si no hay extensión

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
      $name   = $user->getFriendlyName();
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
       * POSICIONES DESDE CONFIG
       * ============================ */
      $pfx = $hasMobile ? 'sig_b1_' : 'sig_b2_';

      $p = static function (string $key, int $default) use ($configsig, $pfx): int {
         return isset($configsig[$pfx . $key]) && $configsig[$pfx . $key] !== ''
                ? (int)$configsig[$pfx . $key]
                : $default;
      };

      /* ============================
       * AJUSTE AUTOMÁTICO NOMBRE
       * ============================ */
      $startX      = $p('nombre_x', 20);
      $nombreY     = $p('nombre_y', 75);
      $maxWidth    = imagesx($img) - $startX - 20;
      $size        = $p('nombre_size', 40);

      while ($size > 20) {
         $bbox       = imagettfbbox($size, 0, $fontblack, $name);
         $text_width = $bbox[2] - $bbox[0];
         if ($text_width <= $maxWidth) break;
         $size--;
      }

      /* ============================
       * TEXTO SOBRE IMAGEN
       * ============================ */
      imagettftext($img, $size, 0, $startX, $nombreY, $white, $fontblack, $name);
      imagettftext($img, $p('titulo_size', 11), 0, $p('titulo_x', 20),  $p('titulo_y', 104),  $white, $fontblack, $titulo);
      imagettftext($img, $p('email_size',  11), 0, $p('email_x',  63),  $p('email_y',  138),  $black, $fontroman, $email);

      /* ============================
       * TELÉFONOS DINÁMICOS
       * ============================ */
      if ($hasMobile) {

         imagettftext($img, $p('mobile_size', 11), 0, $p('mobile_x', 63),  $p('mobile_y', 161), $black, $fontroman, $mobile);
         imagettftext($img, $p('tel_size',    11), 0, $p('tel_x',    185), $p('tel_y',    161), $black, $fontroman, $phone_entity);

         if ($extraPhone !== '') {
            imagettftext($img, $p('ext_size', 11), 0, $p('ext_x', 283), $p('ext_y', 161), $black, $fontroman, $extraLabel . $extraPhone);
         }

      } else {

         imagettftext($img, $p('tel_size', 11), 0, $p('tel_x', 63),  $p('tel_y', 161), $black, $fontroman, $phone_entity);

         if ($extraPhone !== '') {
            imagettftext($img, $p('ext_size', 11), 0, $p('ext_x', 160), $p('ext_y', 161), $black, $fontroman, $extraLabel . $extraPhone);
         }
      }

      imagettftext($img, $p('facebook_size', 11), 0, $p('facebook_x', 63),  $p('facebook_y', 183), $black, $fontroman, $facebook);
      imagettftext($img, $p('web_size',      11), 0, $p('web_x',      185), $p('web_y',      183), $black, $fontroman, $web);

      /* ============================
       * QR SOLO SI HAY CELULAR
       * ============================ */
      if ($include_qr && $hasMobile) {

         require_once GLPI_ROOT . '/vendor/tecnickcom/tcpdf/tcpdf_barcodes_2d.php';

         $mobile_clean = preg_replace('/\D+/', '', $mobile);
         $wa_url       = 'https://wa.me/' . trim($configsig['whatsapp_country_code'] ?? '') . $mobile_clean;

         $barcode = new TCPDF2DBarcode($wa_url, 'QRCODE,M');
         $qr_png  = $barcode->getBarcodePngData(3, 3, [0, 0, 0]);

         $qr_tmp = GLPI_TMP_DIR . '/signature_qr_' . $user->getID() . '.png';
         file_put_contents($qr_tmp, $qr_png);

         if (is_readable($qr_tmp)) {
            $qr = imagecreatefrompng($qr_tmp);
            imagecopy($img, $qr, $p('qr_x', 560), $p('qr_y', 130), 0, 0, 100, 100);
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

   /**
    * Sanitiza el nombre completo de un usuario para usarlo como nombre de archivo.
    * Transliterar UTF-8 → ASCII, reemplazar espacios por _, eliminar caracteres especiales.
    *
    * @param string $name     Nombre a sanitizar (getFriendlyName)
    * @param string $fallback Valor de respaldo si el resultado queda vacío
    */
   public static function sanitizeFilename(string $name, string $fallback = 'user'): string {
      $safe = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: '';
      $safe = preg_replace('/\s+/', '_', $safe);
      $safe = preg_replace('/[^A-Za-z0-9_\-]/', '', $safe);
      return $safe !== '' ? $safe : $fallback;
   }

   /**
    * Construye el cuerpo HTML del correo con variables dinámicas y soporte **negrita**.
    *
    * Variables soportadas: {nombre}, {empresa}, {fecha}
    * Markdown: **texto** → <span style="font-weight:bold">texto</span>
    *
    * @param string $body      Cuerpo principal (texto plano con variables)
    * @param string $footer    Pie opcional (texto plano con variables)
    * @param array  $vars      Mapa ['token' => 'valor'] — ej. ['{nombre}' => 'Juan']
    * @param bool   $isTest    Si es true agrega aviso visual de correo de prueba
    */
   public static function buildEmailHtml(
      string $body,
      string $footer,
      array  $vars,
      bool   $isTest = false
   ): string {

      $tokens = array_keys($vars);
      $values = array_values($vars);

      $render = static function (string $text) use ($tokens, $values): string {
         // 1. Variables
         $text = str_replace($tokens, $values, $text);
         // 2. HTML-escape (previene XSS si los valores contienen caracteres especiales)
         $html = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
         // 3. **negrita** → inline style (más compatible con Outlook que <strong>)
         $html = preg_replace(
            '/\*\*(.+?)\*\*/s',
            '<span style="font-weight:bold">$1</span>',
            $html
         );
         // 4. Saltos de línea
         return nl2br($html);
      };

      $html  = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#333;">';

      if ($isTest) {
         $html .= '<p style="background:#fff3cd;border:1px solid #ffc107;border-radius:4px;'
                . 'padding:8px 12px;font-size:12px;color:#856404;margin-bottom:16px;">'
                . '&#9888; '
                . htmlspecialchars(
                     __('Este es un correo de prueba enviado desde la configuración del plugin.', 'signatures'),
                     ENT_QUOTES,
                     'UTF-8'
                  )
                . '</p>';
      }

      $html .= '<p>' . $render($body) . '</p>';

      if ($footer !== '') {
         $html .= '<hr style="border:none;border-top:1px solid #ddd;margin:12px 0;">';
         $html .= '<p style="font-size:11px;color:#999;">' . $render($footer) . '</p>';
      }

      $html .= '</div>';

      return $html;
   }
}
