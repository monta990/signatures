<?php
declare(strict_types=1);

Session::checkLoginUser();

global $CFG_GLPI;

/* ============================
 * USUARIO
 * ============================ */
$userid = (int)($_GET['userid'] ?? Session::getLoginUserID());

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
$include_qr = !empty($_GET['include_qr']);

/* ============================
 * VALIDACIONES DEL PLUGIN
 * ============================ */
$errors = PluginSignaturesSignature::checkRequirements($include_qr);
if (!empty($errors)) {
   foreach ($errors as $msg) {
      Session::addMessageAfterRedirect($msg, false, ERROR);
   }
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
 * DESCARGA
 * ============================ */
$filename   = PluginSignaturesSignature::sanitizeFilename($user->getFriendlyName(), (string)$userid);
$attachName = 'signature_' . $filename . '.png';

if (ob_get_length()) {
   ob_end_clean();
}

header('Content-Type: image/png');
header('Content-Disposition: attachment; filename="' . $attachName . '"');
header('Content-Length: ' . filesize($file));

readfile($file);
unlink($file);
exit;
