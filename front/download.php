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
   if (ob_get_length()) ob_end_clean();
   Html::redirect($CFG_GLPI['root_doc']);
}

/* ============================
 * CONTROL DE ACCESO
 * ============================ */
$isSelf  = ($userid === (int)Session::getLoginUserID());
$isAdmin = Session::haveRight('config', UPDATE);

if (!$isSelf && !$isAdmin) {
   if (ob_get_length()) ob_end_clean();
   Html::redirect($CFG_GLPI['root_doc']);
}

/* ============================
 * OPCIONES
 * ============================ */
$include_qr = !empty($_GET['include_qr']);
$isPreview  = !empty($_GET['preview']);   // true → inline en el browser (modal)

/* ============================
 * URL DE RETORNO CONFIABLE
 * ============================ */
$backUrl = User::getFormURLWithID($userid) . '&forcetab=PluginSignaturesUser$1';

/* ============================
 * VALIDACIONES DEL PLUGIN
 * ============================ */
$errors = PluginSignaturesSignature::checkRequirements($include_qr);
if (!empty($errors)) {
   foreach ($errors as $msg) {
      Session::addMessageAfterRedirect($msg, false, ERROR);
   }
   if (ob_get_length()) ob_end_clean();
   Html::redirect($backUrl);
}

/* ============================
 * GENERAR PNG
 * ============================ */
try {
   $file = PluginSignaturesSignature::generatePNG($user, $include_qr);
} catch (\Throwable $e) {
   Toolbox::logError('signatures plugin - generatePNG: ' . $e->getMessage());
   Session::addMessageAfterRedirect(
      __('No se pudo generar la firma. Revisa el log de GLPI para más detalles.', 'signatures'),
      false,
      ERROR
   );
   if (ob_get_length()) ob_end_clean();
   Html::redirect($backUrl);
}

/* ============================
 * NOMBRE ARCHIVO — RFC 6266
 * ============================ */
$safeName   = PluginSignaturesSignature::sanitizeFilename($user->getFriendlyName(), (string)$userid);
$attachName = 'signature_' . $safeName . '.png';

// RFC 6266: filename* para nombres con caracteres no-ASCII
$encodedName = rawurlencode($attachName);

/* ============================
 * RESPUESTA HTTP
 * ============================ */
if (ob_get_length()) {
   ob_end_clean();
}

header('Content-Type: image/png');
header('Cache-Control: private, no-store');

if ($isPreview) {
   // Visualización inline para el modal de vista previa
   header('Content-Disposition: inline; filename="' . $attachName . '"');
} else {
   // Descarga estándar con soporte RFC 6266
   header('Content-Disposition: attachment; filename="' . $attachName . '"; filename*=UTF-8\'\'' . $encodedName);
   // Cookie para que el JS del browser detecte que la descarga terminó y restaure el botón
   setcookie('sig_download_done', '1', [
      'expires'  => time() + 60,
      'path'     => '/',
      'secure'   => !empty($_SERVER['HTTPS']),
      'samesite' => 'Strict',
   ]);
}

header('Content-Length: ' . filesize($file));

readfile($file);
unlink($file);
exit;
