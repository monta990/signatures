<?php
declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
   die('Direct access not allowed');
}

Session::checkRight('config', READ);

$resource = $_GET['resource'] ?? '';

switch ($resource) {

   case 'base1':
      $file     = PluginSignaturesPaths::base1Path();
      $mimeType = 'image/png';
      break;

   case 'base2':
      $file     = PluginSignaturesPaths::base2Path();
      $mimeType = 'image/png';
      break;

   case 'font':
      // Servir una fuente de usuario para @font-face en el editor de posiciones.
      // Solo se permite acceder a archivos dentro del directorio de fuentes de usuario.
      $name = basename($_GET['name'] ?? '');
      $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

      if ($name === '' || !in_array($ext, ['ttf', 'otf'], true)) {
         http_response_code(400);
         exit(__('Invalid font resource', 'signatures'));
      }

      $file     = PluginSignaturesPaths::userFontPath($name);
      $mimeType = ($ext === 'otf') ? 'font/otf' : 'font/ttf';
      break;

   default:
      http_response_code(400);
      exit(__('Invalid resource', 'signatures'));
}

if (!is_readable($file)) {
   http_response_code(404);
   exit(__('Resource not found', 'signatures'));
}

$mtime  = filemtime($file);
$etag   = '"' . md5($file . $mtime) . '"';
$ifNone = $_SERVER['HTTP_IF_NONE_MATCH']  ?? '';
$ifMod  = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';

header('Cache-Control: private, max-age=3600');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
header('ETag: ' . $etag);

if (
   ($ifNone !== '' && trim($ifNone) === $etag) ||
   ($ifMod  !== '' && strtotime($ifMod) >= $mtime)
) {
   http_response_code(304);
   exit;
}

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($file));
readfile($file);
exit;
