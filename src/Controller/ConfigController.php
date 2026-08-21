<?php
declare(strict_types=1);

namespace GlpiPlugin\Signatures\Controller;

use Glpi\Controller\AbstractController;
use GlpiPlugin\Signatures\Service\ConfigPage;
use Session;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ConfigController extends AbstractController
{
   #[Route('/config', name: 'signatures_config', methods: ['GET', 'POST'])]
   public function __invoke(Request $request): Response
   {
      // The configuration page performs write operations (settings, templates,
      // fonts and test email), so READ access alone is not sufficient.
      Session::checkRight('config', UPDATE);

      return (new ConfigPage())->handle($request);
   }
}
