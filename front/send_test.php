<?php
declare(strict_types=1);

/**
 * send_test.php — Envía un correo de prueba al administrador que está
 * configurando el plugin, usando los valores guardados actualmente.
 *
 * Solo accesible para usuarios con permiso config UPDATE.
 * Se invoca desde el botón "Enviar correo de prueba" en config.form.php.
 */

// Solo POST — CSRF validado automáticamente por CheckCsrfListener de GLPI
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
   http_response_code(405);
   exit;
}

Session::checkRight('config', UPDATE);

global $CFG_GLPI;

$self    = Plugin::getWebDir('signatures') . '/front/config.form.php';
$backUrl = $_SERVER['HTTP_REFERER'] ?? $self;

/* ============================
 * USUARIO ACTUAL (el admin que hace la prueba)
 * ============================ */
$adminId = (int)Session::getLoginUserID();
$user    = new User();

if (!$user->getFromDB($adminId)) {
   Session::addMessageAfterRedirect(
      __('No se pudo obtener el usuario actual.', 'signatures'),
      false,
      ERROR
   );
   Html::redirect($backUrl);
}

/* ============================
 * OPCIONES
 * include_qr depende de si el admin tiene celular (igual que en download.php)
 * ============================ */
$include_qr = !empty($user->fields['mobile']);

/* ============================
 * VALIDAR PLANTILLAS / FUENTES / GD
 * ============================ */
$errors = PluginSignaturesSignature::checkRequirements($include_qr);
if (!empty($errors)) {
   foreach ($errors as $msg) {
      Session::addMessageAfterRedirect($msg, false, ERROR);
   }
   Html::redirect($backUrl);
}

/* ============================
 * VALIDAR CONFIGURACIÓN DE CORREO
 * ============================ */
$emailErrors = PluginSignaturesSignature::checkEmailConfig();
if (!empty($emailErrors)) {
   foreach ($emailErrors as $msg) {
      Session::addMessageAfterRedirect($msg, false, ERROR);
   }
   Html::redirect($backUrl);
}

/* ============================
 * OBTENER EMAIL DEL ADMINISTRADOR
 * ============================ */
$userEmailAddress = '';
$useremail        = new UserEmail();
$emails           = $useremail->find([
   'users_id'   => $adminId,
   'is_default' => 1,
], [], 1);

if (!empty($emails)) {
   $row              = reset($emails);
   $userEmailAddress = trim($row['email'] ?? '');
}

if ($userEmailAddress === '') {
   Session::addMessageAfterRedirect(
      __('Tu perfil de GLPI no tiene una dirección de correo configurada.', 'signatures'),
      false,
      ERROR
   );
   Html::redirect($backUrl);
}

/* ============================
 * GENERAR PNG
 * ============================ */
try {
   $file = PluginSignaturesSignature::generatePNG($user, $include_qr);
} catch (\Throwable $e) {
   Toolbox::logError('signatures plugin – generatePNG (test): ' . $e->getMessage());
   Session::addMessageAfterRedirect(
      __('No se pudo generar la firma. Revisa el log de GLPI para más detalles.', 'signatures'),
      false,
      ERROR
   );
   Html::redirect($backUrl);
}

/* ============================
 * CONSTRUIR CORREO
 * ============================ */
$config  = PluginSignaturesConfig::getAll();
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

// Asunto (texto plano)
$subject = str_replace(array_keys($vars), array_values($vars), $subject);

$bodyHtml   = PluginSignaturesSignature::buildEmailHtml($body, $footer, $vars, true);
$filename   = PluginSignaturesSignature::sanitizeFilename($user->getFriendlyName(), (string)$adminId);
$attachName = 'signature_' . $filename . '.png';

/* ============================
 * ENVÍO
 * ============================ */
$sent = false;

try {
   $mail = new GLPIMailer();
   $mail->AddAddress($userEmailAddress, $user->getFriendlyName());
   $mail->Subject = '[PRUEBA] ' . $subject;
   $mail->isHTML(true);
   $mail->Body    = $bodyHtml;
   $mail->AddAttachment($file, $attachName, 'base64', 'image/png');
   $sent = $mail->Send();
} catch (\Throwable $e) {
   Toolbox::logError('signatures plugin – GLPIMailer (test): ' . $e->getMessage());
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
      sprintf(__('Correo de prueba enviado a %s.', 'signatures'), $userEmailAddress),
      false,
      INFO
   );
} else {
   Session::addMessageAfterRedirect(
      __('No se pudo enviar el correo de prueba. Verifica la configuración de correo saliente en GLPI.', 'signatures'),
      false,
      ERROR
   );
}

Html::redirect($backUrl);
