<?php
declare(strict_types=1);

Session::checkLoginUser();

global $CFG_GLPI;

/* ============================
 * USUARIO
 * ============================ */
$userid = (int)($_POST['userid'] ?? Session::getLoginUserID());

$user = new User();
if (!$user->getFromDB($userid)) {
   Html::redirect($CFG_GLPI['root_doc']);
}

/* ============================
 * CONTROL DE ACCESO
 * ============================ */
$isSelf  = ($userid === (int)Session::getLoginUserID());
$isAdmin = Session::haveRight('config', UPDATE);

if (!$isSelf && !$isAdmin) {
   Html::redirect($CFG_GLPI['root_doc']);
}

/* ============================
 * OPCIONES
 * ============================ */
$include_qr = !empty($_POST['include_qr']);

/* ============================
 * VALIDAR PLANTILLAS / FUENTES / GD
 * ============================ */
$errors = PluginSignaturesSignature::checkRequirements($include_qr);
if (!empty($errors)) {
   foreach ($errors as $msg) {
      Session::addMessageAfterRedirect($msg, false, ERROR);
   }
   Html::redirect($_SERVER['HTTP_REFERER'] ?? $CFG_GLPI['root_doc']);
}

/* ============================
 * VALIDAR CONFIGURACIÓN DE CORREO
 * ============================ */
$emailErrors = PluginSignaturesSignature::checkEmailConfig();
if (!empty($emailErrors)) {
   foreach ($emailErrors as $msg) {
      Session::addMessageAfterRedirect($msg, false, ERROR);
   }
   Html::redirect($_SERVER['HTTP_REFERER'] ?? $CFG_GLPI['root_doc']);
}

/* ============================
 * OBTENER EMAIL DEL USUARIO
 * ============================ */
$userEmailAddress = '';
$useremail        = new UserEmail();
$emails           = $useremail->find([
   'users_id'   => (int)$user->getID(),
   'is_default' => 1
], [], 1);

if (!empty($emails)) {
   $row              = reset($emails);
   $userEmailAddress = trim($row['email'] ?? '');
}

if ($userEmailAddress === '') {
   Session::addMessageAfterRedirect(
      __('El usuario no tiene una dirección de correo configurada.', 'signatures'),
      false,
      ERROR
   );
   Html::redirect($_SERVER['HTTP_REFERER'] ?? $CFG_GLPI['root_doc']);
}

/* ============================
 * GENERAR PNG
 * ============================ */
$file = PluginSignaturesSignature::generatePNG($user, $include_qr);

/* ============================
 * NOMBRE ARCHIVO SEGURO
 * ============================ */
$filename = $user->getFriendlyName();
$filename = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $filename) ?: '';
$filename = preg_replace('/\s+/', '_', $filename);
$filename = preg_replace('/[^A-Za-z0-9_\-]/', '', $filename);

if ($filename === '') {
   $filename = (string)$userid;
}

$attachName = 'signature_' . $filename . '.png';

/* ============================
 * CONFIGURACIÓN DE CORREO
 * ============================ */
$config  = Config::getConfigurationValues('plugin_signatures');
$subject = trim($config['email_subject'] ?? '');
$body    = trim($config['email_body']    ?? '');
$footer  = trim($config['email_footer']  ?? '');

/* ============================
 * VARIABLES DINÁMICAS
 * Tokens soportados: {nombre}, {empresa}, {fecha}
 * Markdown soportado: **texto** → <span style="font-weight:bold">texto</span>
 * ============================ */
$varNombre = $user->getFriendlyName();

// {empresa} = nombre de la entidad del usuario (igual que plugin responsivas)
// Prioridad: entidad propia del usuario → entidad activa de sesión → nombre de GLPI
$_entityId = (int)($user->fields['entities_id'] ?? 0);
if ($_entityId > 0) {
   $_entity    = new Entity();
   $varEmpresa = $_entity->getFromDB($_entityId)
                 ? ($_entity->fields['name'] ?? '')
                 : '';
} else {
   // Entidad raíz (id = 0): usar completename si está configurado, si no $CFG_GLPI['name']
   $_entity    = new Entity();
   $varEmpresa = $_entity->getFromDB(0)
                 ? ($_entity->fields['name'] ?? ($CFG_GLPI['name'] ?? ''))
                 : ($CFG_GLPI['name'] ?? '');
}
unset($_entity, $_entityId);

$varFecha  = date('d/m/Y');
$varTokens = ['{nombre}', '{empresa}', '{fecha}'];
$varValues = [$varNombre, $varEmpresa, $varFecha];

/**
 * Reemplaza variables dinámicas, escapa HTML y convierte **negrita**.
 *
 * Orden crítico:
 *  1. Reemplazar tokens en texto plano (antes de escapar)
 *  2. htmlspecialchars() → neutraliza HTML inyectado en los valores (XSS)
 *  3. Convertir **texto** → <span style="font-weight:bold"> (los * sobreviven el escape)
 *     Se usa span+style en lugar de <strong> porque Outlook y algunos clientes
 *     de correo pueden despojar etiquetas semánticas pero respetan estilos inline.
 *  4. nl2br() para saltos de línea
 */
$signaturesBoldToHtml = static function (string $text) use ($varTokens, $varValues): string {
    // 1. Variable replacement
    $text = str_replace($varTokens, $varValues, $text);
    // 2. HTML-escape
    $html = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    // 3. **negrita** → <span style="font-weight:bold">negrita</span>
    $html = preg_replace(
        '/\*\*(.+?)\*\*/s',
        '<span style="font-weight:bold">$1</span>',
        $html
    );
    // 4. Newlines → <br>
    $html = nl2br($html);
    return $html;
};

// Asunto: solo variables, sin HTML ni markdown (es texto plano en el header de correo)
$subject = str_replace($varTokens, $varValues, $subject);

/* ============================
 * CONSTRUIR CUERPO HTML (sin imágenes embebidas)
 * ============================ */
$bodyHtml  = '<div style="font-family: Arial, sans-serif; font-size: 14px; color: #333;">';
$bodyHtml .= '<p>' . $signaturesBoldToHtml($body) . '</p>';

if ($footer !== '') {
   $bodyHtml .= '<hr style="border:none;border-top:1px solid #ddd;margin:12px 0;">';
   $bodyHtml .= '<p style="font-size: 11px; color: #999;">' . $signaturesBoldToHtml($footer) . '</p>';
}

$bodyHtml .= '</div>';

/* ============================
 * ENVÍO DE CORREO VÍA GLPI
 * ============================ */
$sent = false;

try {
   $mail = new GLPIMailer();

   $mail->AddAddress($userEmailAddress, $user->getFriendlyName());
   $mail->Subject = $subject;
   $mail->isHTML(true);
   $mail->Body    = $bodyHtml;

   // Firma únicamente como adjunto descargable
   $mail->AddAttachment(
      $file,
      $attachName,
      'base64',
      'image/png'
   );

   $sent = $mail->Send();

} catch (\Throwable $e) {
   $sent = false;
}

/* ============================
 * LIMPIEZA
 * ============================ */
if (is_file($file)) {
   unlink($file);
}

/* ============================
 * RESULTADO
 * ============================ */
if ($sent) {
   Session::addMessageAfterRedirect(
      sprintf(
         __('Firma enviada correctamente a %s.', 'signatures'),
         $userEmailAddress
      ),
      false,
      INFO
   );
} else {
   Session::addMessageAfterRedirect(
      __('No se pudo enviar el correo. Verifica la configuración de correo saliente en GLPI.', 'signatures'),
      false,
      ERROR
   );
}

Html::redirect($_SERVER['HTTP_REFERER'] ?? $CFG_GLPI['root_doc']);
