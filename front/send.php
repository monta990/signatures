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
try {
   $file = PluginSignaturesSignature::generatePNG($user, $include_qr);
} catch (\Throwable $e) {
   Toolbox::logError('signatures plugin – generatePNG: ' . $e->getMessage());
   Session::addMessageAfterRedirect(
      __('No se pudo generar la firma. Revisa el log de GLPI para más detalles.', 'signatures'),
      false,
      ERROR
   );
   Html::redirect($_SERVER['HTTP_REFERER'] ?? $CFG_GLPI['root_doc']);
}

/* ============================
 * CONSTRUIR CORREO
 * ============================ */
$config  = Config::getConfigurationValues('plugin_signatures');
$subject = trim($config['email_subject'] ?? '');
$body    = trim($config['email_body']    ?? '');
$footer  = trim($config['email_footer']  ?? '');

// Variables dinámicas
$_entityId = (int)($user->fields['entities_id'] ?? 0);
$_entity   = new Entity();
if ($_entityId > 0) {
   $varEmpresa = $_entity->getFromDB($_entityId) ? ($_entity->fields['name'] ?? '') : '';
} else {
   $varEmpresa = $_entity->getFromDB(0)
                 ? ($_entity->fields['name'] ?? ($CFG_GLPI['name'] ?? ''))
                 : ($CFG_GLPI['name'] ?? '');
}

$vars = [
   '{nombre}'  => $user->getFriendlyName(),
   '{empresa}' => $varEmpresa,
   '{fecha}'   => date('d/m/Y'),
];

// Asunto (texto plano — solo variables, sin HTML)
$subject = str_replace(array_keys($vars), array_values($vars), $subject);

$bodyHtml   = PluginSignaturesSignature::buildEmailHtml($body, $footer, $vars);
$filename   = PluginSignaturesSignature::sanitizeFilename($user->getFriendlyName(), (string)$userid);
$attachName = 'signature_' . $filename . '.png';

/* ============================
 * ENVÍO
 * ============================ */
$sent = false;

try {
   $mail = new GLPIMailer();
   $mail->AddAddress($userEmailAddress, $user->getFriendlyName());
   $mail->Subject = $subject;
   $mail->isHTML(true);
   $mail->Body    = $bodyHtml;
   $mail->AddAttachment($file, $attachName, 'base64', 'image/png');
   $sent = $mail->Send();
} catch (\Throwable $e) {
   Toolbox::logError('signatures plugin – GLPIMailer: ' . $e->getMessage());
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
      sprintf(__('Firma enviada correctamente a %s.', 'signatures'), $userEmailAddress),
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
