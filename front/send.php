<?php
declare(strict_types=1);

/**
 * send.php — Genera y envía la firma de correo del usuario.
 *
 * Modo normal  ($_POST['is_test'] ausente o '0'):
 *   Envía la firma del usuario indicado en $_POST['userid'] a su correo registrado.
 *   Requiere sesión activa. Solo un admin (config UPDATE) puede enviar la firma
 *   de otro usuario.
 *
 * Modo prueba  ($_POST['is_test'] = '1'):
 *   Envía la firma del administrador actual a su propio correo con prefijo [PRUEBA].
 *   Requiere derecho config UPDATE.
 *   Se invoca desde el botón "Enviar correo de prueba" en config.form.php.
 */

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

Session::checkLoginUser();

global $CFG_GLPI;

$isTest  = (($_POST['is_test'] ?? '0') === '1');
$backUrl = $_SERVER['HTTP_REFERER'] ?? $CFG_GLPI['root_doc'];

/* ============================
 * MODO PRUEBA — requiere config UPDATE
 * ============================ */
if ($isTest) {
    Session::checkRight('config', UPDATE);

    $user = new User();
    if (!$user->getFromDB((int)Session::getLoginUserID())) {
        Session::addMessageAfterRedirect(
            __('No se pudo obtener el usuario actual.', 'signatures'),
            false,
            ERROR
        );
        Html::redirect($backUrl);
    }

    // Para la prueba: incluir QR si el admin tiene celular
    $include_qr = !empty($user->fields['mobile']);

/* ============================
 * MODO NORMAL — envío al usuario indicado
 * ============================ */
} else {
    $userid  = (int)($_POST['userid'] ?? Session::getLoginUserID());
    $isSelf  = ($userid === (int)Session::getLoginUserID());
    $isAdmin = Session::haveRight('config', UPDATE);

    if (!$isSelf && !$isAdmin) {
        Html::redirect($CFG_GLPI['root_doc']);
    }

    $user = new User();
    if (!$user->getFromDB($userid)) {
        Html::redirect($CFG_GLPI['root_doc']);
    }

    $include_qr = !empty($_POST['include_qr']);
}

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
 * CONSTRUIR PAYLOAD DEL CORREO
 * ============================ */
try {
    $payload = PluginSignaturesSignature::buildMailPayload($user, $isTest);
} catch (\RuntimeException $e) {
    Session::addMessageAfterRedirect($e->getMessage(), false, ERROR);
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
    Html::redirect($backUrl);
}

/* ============================
 * ENVÍO
 * ============================ */
$sent = false;

try {
    $mail = new GLPIMailer();

    // ── From con nombre amigable ──────────────────────────────────────────────
    // GLPI guarda el nombre del remitente en from_email_name (campo "Nombre del
    // remitente" en Configuración → Notificaciones). Si no está configurado se
    // cae a admin_email_name. Es exactamente lo que usan las notificaciones nativas.
    $fromName  = trim($CFG_GLPI['from_email_name']  ?? $CFG_GLPI['admin_email_name'] ?? '');
    $fromEmail = trim($CFG_GLPI['from_email']        ?? $CFG_GLPI['admin_email']      ?? '');

    if ($fromEmail !== '' && $fromName !== '') {
        // GLPI 11 usa Symfony Mailer internamente; getEmail() devuelve el objeto Email.
        if (method_exists($mail, 'getEmail')) {
            $mail->getEmail()->from(
                new \Symfony\Component\Mime\Address($fromEmail, $fromName)
            );
        }
    }
    // ─────────────────────────────────────────────────────────────────────────

    $mail->AddAddress($payload['toAddress'], $user->getFriendlyName());
    $mail->Subject = $payload['subject'];
    $mail->isHTML(true);
    $mail->Body    = $payload['bodyHtml'];
    $mail->AddAttachment($file, $payload['attachName'], 'base64', 'image/png');
    $sent = $mail->Send();
} catch (\Throwable $e) {
    Toolbox::logError('signatures plugin - GLPIMailer: ' . $e->getMessage());
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
    $successMsg = $isTest
        ? sprintf(__('Correo de prueba enviado a %s.', 'signatures'), $payload['toAddress'])
        : sprintf(__('Firma enviada correctamente a %s.', 'signatures'), $payload['toAddress']);
    Session::addMessageAfterRedirect($successMsg, false, INFO);
} else {
    $errorMsg = $isTest
        ? __('No se pudo enviar el correo de prueba. Verifica la configuración de correo saliente en GLPI.', 'signatures')
        : __('No se pudo enviar el correo. Verifica la configuración de correo saliente en GLPI.', 'signatures');
    Session::addMessageAfterRedirect($errorMsg, false, ERROR);
}

Html::redirect($backUrl);
