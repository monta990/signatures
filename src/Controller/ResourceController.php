<?php
declare(strict_types=1);

namespace GlpiPlugin\Signatures\Controller;

use Glpi\Controller\AbstractController;
use GlpiPlugin\Signatures\Paths;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Session;

final class ResourceController extends AbstractController
{
   #[Route('/resource', name: 'signatures_resource', methods: ['GET'])]
   public function __invoke(Request $request): Response
   {
      Session::checkRight('config', READ);
      $resource = $request->query->getString('resource');
      if ($resource === 'base1') { $file=Paths::base1Path(); $mime='image/png'; }
      elseif ($resource === 'base2') { $file=Paths::base2Path(); $mime='image/png'; }
      elseif ($resource === 'font') {
         $name=basename($request->query->getString('name')); $ext=strtolower(pathinfo($name, PATHINFO_EXTENSION));
         if ($name === '' || !in_array($ext,['ttf','otf'],true)) throw new BadRequestHttpException(__('Invalid font resource','signatures'));
         $file=Paths::userFontPath($name); $mime=$ext==='otf'?'font/otf':'font/ttf';
      } else throw new BadRequestHttpException(__('Invalid resource','signatures'));
      if (!is_readable($file)) throw new NotFoundHttpException(__('Resource not found','signatures'));
      $mtime=filemtime($file); $etag='"'.md5($file.$mtime).'"';
      if ($request->headers->get('If-None-Match') === $etag || strtotime((string)$request->headers->get('If-Modified-Since')) >= $mtime) {
         return new Response('',304,['ETag'=>$etag]);
      }
      $r=new BinaryFileResponse($file); $r->headers->set('Content-Type',$mime); $r->headers->set('Cache-Control','private, max-age=3600'); $r->headers->set('ETag',$etag); $r->setLastModified((new \DateTimeImmutable())->setTimestamp($mtime)); return $r;
   }
}
