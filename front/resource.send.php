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

header('Cache-Control: private, max-age=3600');
header('Content-Type: image/png');
header('Content-Length: ' . filesize($file));
readfile($file);
exit;