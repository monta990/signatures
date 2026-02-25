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

/* ============================
 * DESCARGA
 * ============================ */
if (ob_get_length()) {
   ob_end_clean();
}

header('Content-Type: image/png');
header('Content-Disposition: attachment; filename="signature_' . $filename . '.png"');
header('Content-Length: ' . filesize($file));

readfile($file);
unlink($file);
exit;