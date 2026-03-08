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
 * CONSTRUIR CUERPO HTML (sin imágenes embebidas)
 * ============================ */
$bodyHtml  = '<div style="font-family: Arial, sans-serif; font-size: 14px; color: #333;">';
$bodyHtml .= '<p>' . nl2br(Html::entities_deep($body)) . '</p>';

if ($footer !== '') {
   $bodyHtml .= '<br>';
   $bodyHtml .= '<p style="font-size: 11px; color: #999;">' . nl2br(Html::entities_deep($footer)) . '</p>';
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
