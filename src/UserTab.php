<?php
declare(strict_types=1);

namespace GlpiPlugin\Signatures;

use CommonGLPI;
use User;
use Html;
use Session;

class UserTab extends CommonGLPI {

   public static function getTypeName($nb = 0): string {
      return __('Email Signatures', 'signatures');
   }

   public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): array {

      if (!$item instanceof User) {
         return [];
      }

      $hasMobile = !empty($item->fields['mobile']);
      $hasBase   = $hasMobile
         ? is_readable(Paths::base1Path())
         : is_readable(Paths::base2Path());
      $hasEmail  = empty(Signature::checkEmailConfig());

      $badge = '';
      if (!$hasBase || !$hasEmail) {
         $badge = " <span class='badge bg-warning ms-1' title='"
                . htmlspecialchars(__('Incomplete configuration', 'signatures'), ENT_QUOTES, 'UTF-8')
                . "'>!</span>";
      }

      return [
         1 => "<span class='d-flex align-items-center'>
                             <i class='ti ti-mail me-2'></i>" .
                             __('Email Signature', 'signatures') .
                             $badge .
                          "</span>"
      ];
   }

   public static function displayTabContentForItem(
      CommonGLPI $item,
      $tabnum = 1,
      $withtemplate = 0
   ): bool {

      if ($item instanceof User) {
         self::showTab($item);
      }

      return true;
   }

   private static function showTab(User $user): void {

      $hasMobile = !empty($user->fields['mobile']);
      $hasBase   = $hasMobile
         ? is_readable(Paths::base1Path())
         : is_readable(Paths::base2Path());

      $emailErrors = Signature::checkEmailConfig();
      $hasEmail    = empty($emailErrors);

      $pluginBase  = Paths::webDir();
      $downloadUrl = Paths::downloadUrl();
      $sendUrl     = Paths::sendUrl();

      Html::displayMessageAfterRedirect();

      Renderer::display(
         'user_tab.html.twig',
         [
            'user_id'        => $user->getID(),
            'user_name'      => $user->getFriendlyName(),
            'has_mobile'     => $hasMobile,
            'has_base'       => $hasBase,
            'has_email'      => $hasEmail,
            'download_url'   => $downloadUrl,
            'send_url'       => $sendUrl,
            'csrf_token'     => Session::getNewCSRFToken(),
            'plugin_web_dir' => $pluginBase,
            'cfg_json'       => json_encode([
               'hasMobile' => $hasMobile,
               'hasBase'   => $hasBase,
               'hasEmail'  => $hasEmail,
               'i18n'      => [
                  'previewError' => __('Error loading preview', 'signatures'),
               ],
            ]),
         ]
      );
   }
}
