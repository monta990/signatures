<?php
declare(strict_types=1);

/**
 * send_test.php — Envía un correo de prueba al administrador que está
 * configurando el plugin, usando los valores guardados actualmente.
 *
 * Solo accesible para usuarios con permiso config UPDATE.
 * Se usa desde el botón "Enviar correo de prueba" en config.form.php.
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
 * VALIDAR PLANTILLAS / FUENTES / GD
 * ============================ */
$config     = Config::getConfigurationValues('plugin_signatures');
$include_qr = !empty(trim($config['whatsapp_country_code'] ?? ''));

$errors = PluginSignaturesSignature::checkRequirements($include_qr);
if (!empty($errors)) {
   foreach ($errors as $msg) {
      Session::addMessageAfterRedirect($msg, false, ERROR);
   }
   Html::redirect($backUrl);
}

/* ============================
 * VALIDAR CONFIGURACIÓN DE CORREO SALIENTE
 * ============================ */
$emailErrors = PluginSignaturesSignature::checkEmailConfig();
if (!empty($emailErrors)) {
   foreach ($emailErrors as $msg) {
      Session::addMessageAfterRedirect($msg, false, ERROR);
   }
   Html::redirect($backUrl);
}

/* ============================
 * OBTENER EMAIL DEL USUARIO ACTUAL
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
 * GENERAR PNG (para el usuario actual)
 * ============================ */
$file = PluginSignaturesSignature::generatePNG($user, $include_qr);

/* ============================
 * NOMBRE DE ARCHIVO SEGURO
 * ============================ */
$filename = $user->getFriendlyName();
$filename = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $filename) ?: '';
$filename = preg_replace('/\s+/', '_', $filename);
$filename = preg_replace('/[^A-Za-z0-9_\-]/', '', $filename);

if ($filename === '') {
   $filename = (string)$adminId;
}

$attachName = 'signature_' . $filename . '.png';

/* ============================
 * VARIABLES DINÁMICAS
 * ============================ */
$varNombre  = $user->getFriendlyName();

$_entityId  = (int)($user->fields['entities_id'] ?? 0);
$_entity    = new Entity();

if ($_entityId > 0) {
   $varEmpresa = $_entity->getFromDB($_entityId)
                 ? ($_entity->fields['name'] ?? '')
                 : '';
} else {
   $varEmpresa = $_entity->getFromDB(0)
                 ? ($_entity->fields['name'] ?? ($CFG_GLPI['name'] ?? ''))
                 : ($CFG_GLPI['name'] ?? '');
}

unset($_entity, $_entityId);

$varFecha  = date('d/m/Y');
$varTokens = ['{nombre}', '{empresa}', '{fecha}'];
$varValues = [$varNombre, $varEmpresa, $varFecha];

$signaturesBoldToHtml = static function (string $text) use ($varTokens, $varValues): string {
   $text = str_replace($varTokens, $varValues, $text);
   $html = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
   $html = preg_replace(
      '/\*\*(.+?)\*\*/s',
      '<span style="font-weight:bold">$1</span>',
      $html
   );
   $html = nl2br($html);
   return $html;
};

/* ============================
 * CONSTRUIR ASUNTO Y CUERPO
 * ============================ */
$subject = trim($config['email_subject'] ?? '');
$body    = trim($config['email_body']    ?? '');
$footer  = trim($config['email_footer']  ?? '');

$subject = str_replace($varTokens, $varValues, $subject);

$bodyHtml  = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#333;">';
// Aviso de que es un correo de prueba
$bodyHtml .= '<p style="background:#fff3cd;border:1px solid #ffc107;border-radius:4px;'
           . 'padding:8px 12px;font-size:12px;color:#856404;margin-bottom:16px;">'
           . '&#9888; ' . __('Este es un correo de prueba enviado desde la configuración del plugin.', 'signatures')
           . '</p>';
$bodyHtml .= '<p>' . $signaturesBoldToHtml($body) . '</p>';

if ($footer !== '') {
   $bodyHtml .= '<hr style="border:none;border-top:1px solid #ddd;margin:12px 0;">';
   $bodyHtml .= '<p style="font-size:11px;color:#999;">' . $signaturesBoldToHtml($footer) . '</p>';
}

$bodyHtml .= '</div>';

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
         __('Correo de prueba enviado a %s.', 'signatures'),
         $userEmailAddress
      ),
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
