<?php
declare(strict_types=1);

Session::checkRight('config', READ);

$resource = $_GET['resource'] ?? '';

switch ($resource) {

   case 'base1':
      $file = PluginSignaturesPaths::base1Path();
      break;

   case 'base2':
      $file = PluginSignaturesPaths::base2Path();
      break;

   default:
      http_response_code(404);
      exit('Recurso inválido');
}

if (!is_readable($file)) {
   http_response_code(404);
   exit('No encontrado');
}

$mtime   = filemtime($file);
$etag    = '"' . md5($file . $mtime) . '"';
$ifNone  = $_SERVER['HTTP_IF_NONE_MATCH']  ?? '';
$ifMod   = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';

header('Cache-Control: private, max-age=3600');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
header('ETag: ' . $etag);

// 304 Not Modified si el cliente ya tiene la versión actual
if (
   ($ifNone !== '' && trim($ifNone) === $etag) ||
   ($ifMod  !== '' && strtotime($ifMod) >= $mtime)
) {
   http_response_code(304);
   exit;
}

header('Content-Type: image/png');
header('Content-Length: ' . filesize($file));
readfile($file);
exit;