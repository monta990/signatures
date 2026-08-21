<?php
declare(strict_types=1);

namespace GlpiPlugin\Signatures\Controller;

use Glpi\Controller\AbstractController;
use GlpiPlugin\Signatures\Signature;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use User;
use Session;
use Toolbox;

final class DownloadController extends AbstractController
{
   #[Route('/download', name: 'signatures_download', methods: ['GET'])]
   public function __invoke(Request $request): Response
   {
      Session::checkLoginUser();
      $userid = $request->query->getInt('userid', (int)Session::getLoginUserID());
      $user = new User();
      if ($userid <= 0 || !$user->getFromDB($userid)) {
         throw new NotFoundHttpException();
      }
      $isSelf = $userid === (int)Session::getLoginUserID();
      $isAdmin = Session::haveRight('config', UPDATE);
      if (!$isSelf && !$isAdmin) {
         throw new AccessDeniedHttpException();
      }

      $includeQr = $request->query->getBoolean('include_qr', false);
      $preview = $request->query->getBoolean('preview', false);
      $errors = Signature::checkRequirements($includeQr);
      if ($errors !== []) {
         foreach ($errors as $msg) Session::addMessageAfterRedirect($msg, false, ERROR);
         return new \Symfony\Component\HttpFoundation\RedirectResponse(\User::getFormURLWithID($userid) . '&forcetab=GlpiPlugin\\Signatures\\UserTab$1');
      }

      try {
         $file = Signature::generatePNG($user, $includeQr);
      } catch (\Throwable $e) {
         Toolbox::logInFile('php-errors', 'signatures plugin - generatePNG: ' . $e->getMessage(), true, false);
         Session::addMessageAfterRedirect(__('Could not generate the signature. Check the GLPI log for details.', 'signatures'), false, ERROR);
         return new \Symfony\Component\HttpFoundation\RedirectResponse(\User::getFormURLWithID($userid) . '&forcetab=GlpiPlugin\\Signatures\\UserTab$1');
      }

      $safeName = Signature::sanitizeFilename($user->getFriendlyName(), (string)$userid);
      $attachName = 'signature_' . $safeName . '.png';
      $response = new BinaryFileResponse($file);
      $response->headers->set('Content-Type', 'image/png');
      $response->headers->set('Cache-Control', 'private, no-store');
      $response->setContentDisposition($preview ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT, $attachName, $attachName);
      if (!$preview) {
         $response->headers->setCookie(new \Symfony\Component\HttpFoundation\Cookie('sig_download_done', '1', time() + 60, '/', null, !empty($_SERVER['HTTPS']), true, false, 'strict'));
      }
      $response->deleteFileAfterSend(true);
      return $response;
   }
}
