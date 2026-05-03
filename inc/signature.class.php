<?php
declare(strict_types=1);

class PluginSignaturesSignature {

   public static function checkRequirements(bool $include_qr = false): array {

      $errors = [];

      $base1 = PluginSignaturesPaths::base1Path();
      $base2 = PluginSignaturesPaths::base2Path();

      if (!is_readable($base1) || !is_readable($base2)) {
         $errors[] = __('Templates are not correctly loaded. Please check the plugin configuration.', 'signatures');
      }

      $font = PluginSignaturesPaths::resolvedFontName();
      if (!is_readable($font)) {
         $errors[] = sprintf(__('TTF font not found: %s', 'signatures'), $font);
      }

      $font2 = PluginSignaturesPaths::resolvedFontBody();
      if (!is_readable($font2)) {
         $errors[] = sprintf(__('TTF font not found: %s', 'signatures'), $font2);
      }

      if (!extension_loaded('gd')) {
         $errors[] = __('PHP GD extension is required.', 'signatures');
      }

      if ($include_qr) {
         $configsig    = PluginSignaturesConfig::getAll();
         $countryCode  = trim($configsig['whatsapp_country_code'] ?? '');

         if ($countryCode === '') {
            $errors[] = __('WhatsApp country code is not configured. Define it in the plugin settings.', 'signatures');
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
      $config  = PluginSignaturesConfig::getAll();
      $subject = trim($config['email_subject'] ?? '');
      $body    = trim($config['email_body']    ?? '');

      if ($subject === '') {
         $errors[] = __('Email subject is not configured. Define it in the plugin settings.', 'signatures');
      }

      if ($body === '') {
         $errors[] = __('Email body is not configured. Define it in the plugin settings.', 'signatures');
      }

      return $errors;
   }

   /**
    * Genera la firma en PNG y devuelve la ruta del archivo temporal.
    */
   public static function generatePNG(User $user, bool $include_qr): string {

      $configsig = PluginSignaturesConfig::getAll();
      $facebook  = $configsig['facebook_page']  ?? '';
      $x_page    = $configsig['x_page']         ?? '';
      $linkedin  = $configsig['linkedin_page']  ?? '';
      $instagram = $configsig['instagram_page'] ?? '';
      $snapchat  = $configsig['snapchat_page']  ?? '';
      $tiktok    = $configsig['tiktok_page']    ?? '';

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
         $extraLabel = __('Office:', 'signatures') . ' ';
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
            sprintf(__('Could not load template: %s', 'signatures'), $basefile)
         );
      }

      imagesavealpha($img, true);
      imagealphablending($img, true);

      $black = imagecolorallocate($img, 0, 0, 0);
      $white = imagecolorallocate($img, 255, 255, 255);

      $fontblack = PluginSignaturesPaths::resolvedFontName();
      $fontroman = PluginSignaturesPaths::resolvedFontBody();

      /* ============================
       * ENTIDAD
       * ============================ */
      $entity = new Entity();
      if (!$entity->getFromDB((int)$user->fields['entities_id'])) {
         throw new RuntimeException(__('Invalid entity', 'signatures'));
      }

      /* ============================
       * DATOS USUARIO
       * ============================ */
      $name   = $user->getFriendlyName();
      $titulo = __('Not specified', 'signatures');
      $email  = __('Not specified', 'signatures');

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

      $_defaults = PluginSignaturesConfig::getDefaults();
      $p = static function (string $key, int $default = 0) use ($configsig, $pfx, $_defaults): int {
         if (isset($configsig[$pfx . $key]) && $configsig[$pfx . $key] !== '') {
            return (int)$configsig[$pfx . $key];
         }
         return isset($_defaults[$pfx . $key]) ? (int)$_defaults[$pfx . $key] : $default;
      };

      // Devuelve true si el campo está habilitado (default: true)
      $en = static function (string $field) use ($configsig, $pfx): bool {
         return ($configsig[$pfx . $field . '_enabled'] ?? '1') !== '0';
      };

      /* ============================
       * AJUSTE AUTOMÁTICO NOMBRE
       * ============================ */
      $startX      = $p('nombre_x', 20);
      $nombreY     = $p('nombre_y', 75);
      $maxWidth    = imagesx($img) - $startX - 20;
      $size        = $p('nombre_size', 40);

      // Bajar tamaño si el nombre es demasiado ancho
      while ($size > 20) {
         $bbox       = imagettfbbox($size, 0, $fontblack, $name);
         $text_width = $bbox[2] - $bbox[0];
         if ($text_width <= $maxWidth) break;
         $size--;
      }

      // Subir tamaño si hay espacio y el configurado permite más
      $configured = $p('nombre_size', 40);
      while ($size < $configured) {
         $bbox       = imagettfbbox($size + 1, 0, $fontblack, $name);
         $text_width = $bbox[2] - $bbox[0];
         if ($text_width > $maxWidth) break;
         $size++;
      }

      /* ============================
       * TEXTO SOBRE IMAGEN
       * ============================ */
      if ($en('nombre')) {
         imagettftext($img, $size, 0, $startX, $nombreY, $white, $fontblack, $name);
      }
      if ($en('titulo')) {
         imagettftext($img, $p('titulo_size', 11), 0, $p('titulo_x', 20), $p('titulo_y', 104), $white, $fontblack, $titulo);
      }
      if ($en('email')) {
         imagettftext($img, $p('email_size', 11), 0, $p('email_x', 63), $p('email_y', 138), $black, $fontroman, $email);
      }

      /* ============================
       * TELÉFONOS DINÁMICOS
       * ============================ */
      if ($hasMobile) {

         if ($en('mobile')) {
            imagettftext($img, $p('mobile_size', 11), 0, $p('mobile_x', 63), $p('mobile_y', 161), $black, $fontroman, $mobile);
         }
         if ($en('tel')) {
            imagettftext($img, $p('tel_size', 11), 0, $p('tel_x', 185), $p('tel_y', 161), $black, $fontroman, $phone_entity);
         }
         if ($extraPhone !== '' && $en('ext')) {
            imagettftext($img, $p('ext_size', 11), 0, $p('ext_x', 283), $p('ext_y', 161), $black, $fontroman, $extraLabel . $extraPhone);
         }

      } else {

         if ($en('tel')) {
            imagettftext($img, $p('tel_size', 11), 0, $p('tel_x', 63), $p('tel_y', 161), $black, $fontroman, $phone_entity);
         }
         if ($extraPhone !== '' && $en('ext')) {
            imagettftext($img, $p('ext_size', 11), 0, $p('ext_x', 160), $p('ext_y', 161), $black, $fontroman, $extraLabel . $extraPhone);
         }
      }

      if ($en('facebook')) {
         imagettftext($img, $p('facebook_size', 11), 0, $p('facebook_x', 63), $p('facebook_y', 183), $black, $fontroman, $facebook);
      }
      if ($en('web')) {
         imagettftext($img, $p('web_size', 11), 0, $p('web_x', 185), $p('web_y', 183), $black, $fontroman, $web);
      }
      if ($x_page !== '' && $en('x')) {
         imagettftext($img, $p('x_size', 11), 0, $p('x_x', 63), $p('x_y', 205), $black, $fontroman, $x_page);
      }
      if ($linkedin !== '' && $en('linkedin')) {
         imagettftext($img, $p('linkedin_size', 11), 0, $p('linkedin_x', 185), $p('linkedin_y', 205), $black, $fontroman, $linkedin);
      }
      if ($instagram !== '' && $en('instagram')) {
         imagettftext($img, $p('instagram_size', 11), 0, $p('instagram_x', 320), $p('instagram_y', 205), $black, $fontroman, $instagram);
      }
      if ($snapchat !== '' && $en('snapchat')) {
         imagettftext($img, $p('snapchat_size', 11), 0, $p('snapchat_x', 450), $p('snapchat_y', 205), $black, $fontroman, $snapchat);
      }
      if ($tiktok !== '' && $en('tiktok')) {
         imagettftext($img, $p('tiktok_size', 11), 0, $p('tiktok_x', 63), $p('tiktok_y', 227), $black, $fontroman, $tiktok);
      }

      /* ============================
       * QR SOLO SI HAY CELULAR
       * ============================ */
      if ($include_qr && $hasMobile && $en('qr')) {

         require_once GLPI_ROOT . '/vendor/tecnickcom/tcpdf/tcpdf_barcodes_2d.php';

         $mobile_clean = preg_replace('/\D+/', '', $mobile);
         $wa_url       = 'https://wa.me/' . trim($configsig['whatsapp_country_code'] ?? '') . $mobile_clean;

         $barcode = new TCPDF2DBarcode($wa_url, 'QRCODE,M');
         $qr_png  = $barcode->getBarcodePngData(3, 3, [0, 0, 0]);

         $qr_tmp = GLPI_TMP_DIR . '/signature_qr_' . $user->getID() . '.png';
         file_put_contents($qr_tmp, $qr_png);

         if (is_readable($qr_tmp)) {
            $qr    = imagecreatefrompng($qr_tmp);
            $qr_w  = imagesx($qr);
            $qr_h  = imagesy($qr);
            // Escalar siempre a 100×100 para coincidencia exacta con el placeholder del editor
            imagecopyresampled($img, $qr, $p('qr_x', 560), $p('qr_y', 130), 0, 0, 100, 100, $qr_w, $qr_h);
            unset($qr);
            unlink($qr_tmp);
         }
      }

      /* ============================
       * SALIDA FINAL
       * ============================ */
      $out = GLPI_TMP_DIR . '/signature_' . $user->getID() . '_' . uniqid('', true) . '.png';
      imagepng($img, $out);
      unset($img);

      return $out;
   }

   /**
    * Valida que un archivo subido sea un font TTF u OTF real mediante magic bytes.
    *
    * Magic bytes:
    *  - TTF: 00 01 00 00  (TrueType)
    *  - TTF: 74 72 75 65  ("true" — Apple TrueType)
    *  - OTF: 4F 54 54 4F  ("OTTO" — OpenType CFF)
    *
    * @param string $path Ruta al archivo temporal subido.
    * @return bool True si el archivo es un font válido.
    */
   public static function validateFontFile(string $path): bool {
      if (!is_readable($path)) {
         return false;
      }

      $handle = fopen($path, 'rb');
      if ($handle === false) {
         return false;
      }

      $magic = fread($handle, 4);
      fclose($handle);

      if ($magic === false || strlen($magic) < 4) {
         return false;
      }

      $signatures = [
         "\x00\x01\x00\x00",  // TrueType
         "true",               // Apple TrueType
         "OTTO",               // OpenType CFF
      ];

      return in_array($magic, $signatures, true);
   }

   /**
    * Sanitiza el nombre de un archivo de fuente para guardarlo en disco de forma segura.
    * Solo permite letras, números, guiones y guiones bajos. Conserva la extensión.
    *
    * @param string $originalName Nombre original del archivo subido.
    * @return string|null Nombre seguro con extensión, o null si la extensión no es válida.
    */
   public static function sanitizeFontFilename(string $originalName): ?string {
      $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
      if (!in_array($ext, ['ttf', 'otf'], true)) {
         return null;
      }

      $base = pathinfo($originalName, PATHINFO_FILENAME);
      $base = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $base) ?: '';
      $base = preg_replace('/\s+/', '_', $base);
      $base = preg_replace('/[^A-Za-z0-9_\-]/', '', $base);
      $base = trim($base, '_-');

      if ($base === '') {
         $base = 'font_' . uniqid('', true);
      }

      return $base . '.' . $ext;
   }

   /**
    * Reads the human-readable font name from a TTF or OTF file's internal `name` table.
    * Uses pure PHP binary reading — no external libraries required.
    *
    * Priority:
    *   1. nameID 4 (Full font name, e.g. "Avenir Black") — platformID 3 (Windows, UTF-16BE)
    *   2. nameID 4 — platformID 1 (Mac, Latin-1)
    *   3. nameID 1 (Font family, e.g. "Avenir") — same platform priority
    *   4. Filename basename (safe fallback if parsing fails)
    *
    * @param string $path  Full path to the TTF/OTF file.
    * @return string       Display name, or the filename basename on failure.
    */
   public static function readFontDisplayName(string $path): string {
      $fallback = pathinfo($path, PATHINFO_FILENAME);

      $fh = @fopen($path, 'rb');
      if ($fh === false) {
         return $fallback;
      }

      try {
         // ── Offset table ────────────────────────────────────────────────────
         // sfVersion (4) + numTables (2) + searchRange (2) + entrySelector (2) + rangeShift (2)
         $header = fread($fh, 12);
         if ($header === false || strlen($header) < 12) {
            return $fallback;
         }

         $numTables = unpack('n', substr($header, 4, 2))[1];

         // ── Table directory ─────────────────────────────────────────────────
         // Each entry: tag (4) + checkSum (4) + offset (4) + length (4) = 16 bytes
         $nameOffset = null;
         for ($i = 0; $i < $numTables; $i++) {
            $entry = fread($fh, 16);
            if ($entry === false || strlen($entry) < 16) {
               break;
            }
            $tag = substr($entry, 0, 4);
            if ($tag === 'name') {
               $nameOffset = unpack('N', substr($entry, 8, 4))[1];
               break;
            }
         }

         if ($nameOffset === null) {
            return $fallback;
         }

         // ── name table header ───────────────────────────────────────────────
         // format (2) + count (2) + stringOffset (2)
         fseek($fh, $nameOffset);
         $nameHeader = fread($fh, 6);
         if ($nameHeader === false || strlen($nameHeader) < 6) {
            return $fallback;
         }

         $count        = unpack('n', substr($nameHeader, 2, 2))[1];
         $stringOffset = unpack('n', substr($nameHeader, 4, 2))[1];
         $stringsBase  = $nameOffset + $stringOffset;

         // ── Name records ────────────────────────────────────────────────────
         // platformID (2) + encodingID (2) + languageID (2) + nameID (2) + length (2) + offset (2)
         $candidates = []; // [nameID][platformID] = ['offset'=>, 'length'=>]
         for ($i = 0; $i < $count; $i++) {
            $rec = fread($fh, 12);
            if ($rec === false || strlen($rec) < 12) {
               break;
            }
            $platformID = unpack('n', substr($rec, 0, 2))[1];
            $nameID     = unpack('n', substr($rec, 6, 2))[1];
            $length     = unpack('n', substr($rec, 8, 2))[1];
            $strOff     = unpack('n', substr($rec, 10, 2))[1];

            // Collect nameID 4 (full name) and nameID 1 (family) only
            if ($nameID === 4 || $nameID === 1) {
               $candidates[$nameID][$platformID] = [
                  'offset' => $stringsBase + $strOff,
                  'length' => $length,
               ];
            }
         }

         // ── Resolve best candidate ──────────────────────────────────────────
         $resolve = static function (int $nameID) use ($fh, $candidates): ?string {
            if (!isset($candidates[$nameID])) {
               return null;
            }

            // Prefer platform 3 (Windows, UTF-16BE) then platform 1 (Mac, Latin-1)
            foreach ([3, 1] as $pid) {
               if (!isset($candidates[$nameID][$pid])) {
                  continue;
               }
               $rec = $candidates[$nameID][$pid];
               fseek($fh, $rec['offset']);
               $raw = fread($fh, $rec['length']);
               if ($raw === false || $raw === '') {
                  continue;
               }

               $name = ($pid === 3)
                  ? mb_convert_encoding($raw, 'UTF-8', 'UTF-16BE')
                  : $raw;

               $name = trim($name);
               if ($name !== '') {
                  return $name;
               }
            }

            return null;
         };

         // nameID 4 first, then nameID 1 as fallback
         $name = $resolve(4) ?? $resolve(1);
         return ($name !== null && $name !== '') ? $name : $fallback;

      } finally {
         fclose($fh);
      }
   }

   /**
    * Sanitizes a user's full name for use as a filename.
    * Transliterates UTF-8 → ASCII, replaces spaces with _, removes special characters.
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
         // 3. Marcado inline → inline styles (compatibles con Outlook)
         //    El contenido capturado ya está escapado por htmlspecialchars en el paso 2,
         //    así que es seguro insertarlo directamente como texto HTML.
         //    Orden de evaluación: **bold** primero, luego *italic*, luego __underline__
         //    para evitar que *texto* capture parte de **texto**.
         $html = preg_replace_callback(
            '/\*\*(.+?)\*\*/s',
            static fn(array $m): string => '<span style="font-weight:bold">' . $m[1] . '</span>',
            $html
         );
         $html = preg_replace_callback(
            '/\*(.+?)\*/s',
            static fn(array $m): string => '<span style="font-style:italic">' . $m[1] . '</span>',
            $html
         );
         $html = preg_replace_callback(
            '/__(.+?)__/s',
            static fn(array $m): string => '<span style="text-decoration:underline">' . $m[1] . '</span>',
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
                     __('This is a test email sent from the plugin configuration.', 'signatures'),
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

   /**
    * Construye el payload completo del correo (asunto, HTML, adjunto, destinatario).
    * Centraliza la lógica duplicada entre send.php y send_test.php.
    *
    * @param User $user    Usuario destinatario / propietario de la firma
    * @param bool $isTest  Si es true, agrega prefijo [PRUEBA] y aviso visual
    * @return array{subject:string, bodyHtml:string, attachName:string, toAddress:string}
    * @throws RuntimeException Si el usuario no tiene correo registrado
    */
   public static function buildMailPayload(User $user, bool $isTest = false): array {

      global $CFG_GLPI;

      // Dirección de destino
      $toAddress = '';
      $useremail = new UserEmail();
      $emails    = $useremail->find(['users_id' => (int)$user->getID(), 'is_default' => 1], [], 1);
      if (!empty($emails)) {
         $row       = reset($emails);
         $toAddress = trim($row['email'] ?? '');
      }
      if ($toAddress === '') {
         throw new RuntimeException(
            __('The user does not have an email address configured.', 'signatures')
         );
      }

      // Variables dinámicas
      $entityId = (int)($user->fields['entities_id'] ?? 0);
      $entity   = new Entity();
      if ($entityId > 0) {
         $varEmpresa = $entity->getFromDB($entityId) ? ($entity->fields['name'] ?? '') : '';
      } else {
         $varEmpresa = $entity->getFromDB(0)
                       ? ($entity->fields['name'] ?? ($CFG_GLPI['name'] ?? ''))
                       : ($CFG_GLPI['name'] ?? '');
      }

      $vars = [
         '{nombre}'  => $user->getFriendlyName(),
         '{empresa}' => $varEmpresa,
         '{fecha}'   => date('d/m/Y'),
      ];

      // Asunto y cuerpo desde configuración
      $config  = PluginSignaturesConfig::getAll();
      $subject = trim($config['email_subject'] ?? '');
      $body    = trim($config['email_body']    ?? '');
      $footer  = trim($config['email_footer']  ?? '');

      // Sustituir variables en el asunto (texto plano)
      $subject = str_replace(array_keys($vars), array_values($vars), $subject);
      if ($isTest) {
         $subject = '[PRUEBA] ' . $subject;
      }

      $bodyHtml   = self::buildEmailHtml($body, $footer, $vars, $isTest);
      $attachName = 'signature_' . self::sanitizeFilename($user->getFriendlyName(), (string)$user->getID()) . '.png';

      return compact('subject', 'bodyHtml', 'attachName', 'toAddress');
   }
}
