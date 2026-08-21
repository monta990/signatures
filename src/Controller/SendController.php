<?php
declare(strict_types=1);

namespace GlpiPlugin\Signatures\Controller;

use Glpi\Controller\AbstractController;
use GlpiPlugin\Signatures\Paths;
use GlpiPlugin\Signatures\Signature;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use User;
use Session;
use GLPIMailer;
use Toolbox;

final class SendController extends AbstractController
{
   #[Route('/send', name: 'signatures_send', methods: ['POST'])]
   public function __invoke(Request $request): Response
   {
      Session::checkLoginUser();
      $isTest=$request->request->getString('is_test','0')==='1';
      if ($isTest) { Session::checkRight('config',UPDATE); $userid=(int)Session::getLoginUserID(); $includeQr=false; }
      else { $userid=$request->request->getInt('userid',(int)Session::getLoginUserID()); $includeQr=$request->request->getBoolean('include_qr',false); if ($userid !== (int)Session::getLoginUserID() && !Session::haveRight('config',UPDATE)) throw new AccessDeniedHttpException(); }
      $user=new User(); if (!$user->getFromDB($userid)) throw new \Glpi\Exception\Http\NotFoundHttpException();
      $back=$isTest?Paths::configUrl():User::getFormURLWithID($userid).'&forcetab=GlpiPlugin\\Signatures\\UserTab$1';
      $requirements = Signature::checkRequirements($includeQr);
      if ($requirements !== []) {
         foreach ($requirements as $msg) {
            Session::addMessageAfterRedirect($msg, false, ERROR);
         }
         return new RedirectResponse($back);
      }
      $emailErrors=Signature::checkEmailConfig(); if ($emailErrors!==[]) { foreach($emailErrors as $msg) Session::addMessageAfterRedirect($msg,false,ERROR); return new RedirectResponse($back); }
      try { $payload=Signature::buildMailPayload($user,$isTest); $file=Signature::generatePNG($user,$includeQr); $mail=new GLPIMailer(); $email=$mail->getEmail(); $email->to(new \Symfony\Component\Mime\Address($payload['toAddress'],$user->getFriendlyName())); $email->subject($payload['subject']); $email->html($payload['bodyHtml']); $email->attachFromPath($file,$payload['attachName'],'image/png'); $sent=$mail->send(); } catch(\Throwable $e) { $sent=false; Toolbox::logInFile('mail','signatures plugin: send failed (user ID: '.$userid.')'); }
      if (isset($file) && is_file($file)) unlink($file);
      if ($sent) { Session::addMessageAfterRedirect($isTest?sprintf(__('Test email sent to %s.','signatures'),$payload['toAddress']):sprintf(__('Signature successfully sent to %s.','signatures'),$payload['toAddress']),false,INFO); }
      else { Session::addMessageAfterRedirect($isTest?__('Could not send the test email. Check the outgoing mail configuration in GLPI.','signatures'):__('Could not send the email. Please check the outgoing mail configuration in GLPI.','signatures'),false,ERROR); }
      return new RedirectResponse($back);
   }
}
