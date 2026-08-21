<?php
declare(strict_types=1);

namespace GlpiPlugin\Signatures\Service;


use UserEmail;
use Html;
use Dropdown;
use Entity;
use User;
use Config as GLPIConfig;
use Session;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ConfigPage
{
   public function handle(Request $request): Response
   {
      // Keep the service safe even if it is ever invoked outside the controller.
      Session::checkRight('config', UPDATE);

      ob_start();

$post = $request->request->all();
$query = $request->query->all();
$files = [];
foreach ($request->files->all() as $key => $uploaded) {
   if ($uploaded instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
      $files[$key] = [
         'tmp_name' => $uploaded->getPathname(),
         'size'     => $uploaded->getSize(),
         'name'     => $uploaded->getClientOriginalName(),
      ];
   } elseif (is_array($uploaded)) {
      $files[$key] = $uploaded;
   }
}

$self = \GlpiPlugin\Signatures\Paths::configUrl();
$validTabs = ['general','cel','nocel','positions','fonts'];
$activeTab = in_array($post['active_tab'] ?? '', $validTabs, true)
   ? $post['active_tab']
   : (in_array($query['tab'] ?? '', $validTabs, true) ? $query['tab'] : 'general');

/* ========================== CONFIG ========================== */

$maxSize     = 300 * 1024;
$maxWidth    = 4096;
$maxHeight   = 2048;
$allowedMime = ['image/png'];

$fontMaxSize = 2 * 1024 * 1024;

$baseDir   = \GlpiPlugin\Signatures\Paths::filesDir();
$base1File = \GlpiPlugin\Signatures\Paths::base1Path();
$base2File = \GlpiPlugin\Signatures\Paths::base2Path();

$userFontsDir = \GlpiPlugin\Signatures\Paths::userFontsDir();

$hasbase1  = is_readable($base1File);
$hasbase2  = is_readable($base2File);

if (!is_dir($baseDir)) {
   mkdir($baseDir, 0755, true);
}

if (!is_dir($userFontsDir)) {
   mkdir($userFontsDir, 0755, true);
}

/* ========================== DELETE ========================== */

if (isset($post['delete_base1']) && $hasbase1) {
   unlink($base1File);
   Session::addMessageAfterRedirect(__('Mobile template deleted', 'signatures'), false, INFO);
   return new \Symfony\Component\HttpFoundation\RedirectResponse($self . '?deleted=1');
}

if (isset($post['delete_base2']) && $hasbase2) {
   unlink($base2File);
   Session::addMessageAfterRedirect(__('No-mobile template deleted', 'signatures'), false, INFO);
   return new \Symfony\Component\HttpFoundation\RedirectResponse($self . '?deleted=1');
}

if (isset($post['delete_font'])) {
   $fontToDelete = basename(trim($post['delete_font'] ?? ''));
   $ext          = strtolower(pathinfo($fontToDelete, PATHINFO_EXTENSION));

   if ($fontToDelete !== '' && in_array($ext, ['ttf', 'otf'], true)) {
      $fontPath = \GlpiPlugin\Signatures\Paths::userFontPath($fontToDelete);
      if (is_file($fontPath)) {
         $cfg = \GlpiPlugin\Signatures\Config::getAll();
         foreach (['font_name', 'font_body'] as $key) {
            if (($cfg[$key] ?? '') === $fontToDelete) {
               GLPIConfig::setConfigurationValues('plugin_signatures', [$key => '']);
            }
         }
         \GlpiPlugin\Signatures\Config::invalidate();
         unlink($fontPath);
         Session::addMessageAfterRedirect(__('Font deleted', 'signatures'), false, INFO);
      }
   }
   return new \Symfony\Component\HttpFoundation\RedirectResponse($self . '?deleted=1&tab=fonts#tab-fonts');
}

/* ========================== SAVE ========================== */

if (isset($post['save'])) {

   if (isset($post['facebook_page'])) {
      GLPIConfig::setConfigurationValues(
         'plugin_signatures',
         ['facebook_page' => trim($post['facebook_page'])]
      );
   }

   foreach (['x_page', 'linkedin_page', 'instagram_page', 'snapchat_page', 'tiktok_page'] as $_snKey) {
      if (isset($post[$_snKey])) {
         GLPIConfig::setConfigurationValues('plugin_signatures', [$_snKey => trim($post[$_snKey])]);
      }
   }

   if (isset($post['whatsapp_country_code'])) {
      GLPIConfig::setConfigurationValues(
         'plugin_signatures',
         ['whatsapp_country_code' => preg_replace('/[^0-9]/', '', trim($post['whatsapp_country_code']))]
      );
   }

   if (isset($post['email_subject'])) {
      GLPIConfig::setConfigurationValues('plugin_signatures', ['email_subject' => trim($post['email_subject'])]);
   }
   if (isset($post['email_body'])) {
      GLPIConfig::setConfigurationValues('plugin_signatures', ['email_body' => trim($post['email_body'])]);
   }
   if (isset($post['email_footer'])) {
      GLPIConfig::setConfigurationValues('plugin_signatures', ['email_footer' => trim($post['email_footer'])]);
   }

   foreach (['base1' => $base1File, 'base2' => $base2File] as $field => $dest) {
      if (!isset($files[$field]) || !is_uploaded_file($files[$field]['tmp_name'])) {
         continue;
      }
      $tmp  = $files[$field]['tmp_name'];
      $size = $files[$field]['size'];
      $finfo = new \finfo(FILEINFO_MIME_TYPE);
      $mime  = $finfo->file($tmp);
      unset($finfo);

      if ($size > $maxSize) {
         Session::addMessageAfterRedirect(__('File too large (Max. 300 KB)', 'signatures'), false, ERROR);
         return new \Symfony\Component\HttpFoundation\RedirectResponse($self . '?error=1');
      }
      if (!in_array($mime, $allowedMime, true)) {
         Session::addMessageAfterRedirect(__('Invalid format, only PNG files are allowed', 'signatures'), false, ERROR);
         return new \Symfony\Component\HttpFoundation\RedirectResponse($self . '?error=1');
      }
      $dimensions = @getimagesize($tmp);
      if ($dimensions === false || ($dimensions[2] ?? null) !== IMAGETYPE_PNG) {
         Session::addMessageAfterRedirect(__('Could not process the PNG image', 'signatures'), false, ERROR);
         return new \Symfony\Component\HttpFoundation\RedirectResponse($self . '?error=1');
      }
      $width  = (int)($dimensions[0] ?? 0);
      $height = (int)($dimensions[1] ?? 0);
      if ($width < 1 || $height < 1 || $width > $maxWidth || $height > $maxHeight) {
         Session::addMessageAfterRedirect(
            __('PNG dimensions are too large. Maximum allowed size is 4096 × 2048 px.', 'signatures'),
            false,
            ERROR
         );
         return new \Symfony\Component\HttpFoundation\RedirectResponse($self . '?error=1');
      }

      $image = @imagecreatefrompng($tmp);
      if ($image === false) {
         Session::addMessageAfterRedirect(__('Could not process the PNG image', 'signatures'), false, ERROR);
         return new \Symfony\Component\HttpFoundation\RedirectResponse($self . '?error=1');
      }
      $written = @imagepng($image, $dest);
      imagedestroy($image);
      if (!$written) {
         if (is_file($dest)) {
            @unlink($dest);
         }
         Session::addMessageAfterRedirect(__('Could not process the PNG image', 'signatures'), false, ERROR);
         return new \Symfony\Component\HttpFoundation\RedirectResponse($self . '?error=1');
      }
      @chmod($dest, 0644);
   }

   $_builtinFonts = [\GlpiPlugin\Signatures\Paths::BUILTIN_FONT_NAME, \GlpiPlugin\Signatures\Paths::BUILTIN_FONT_BODY];
   foreach (['font_name', 'font_body'] as $key) {
      if (isset($post[$key])) {
         $val = basename(trim($post[$key]));
         if ($val === '' || in_array($val, $_builtinFonts, true) || is_readable(\GlpiPlugin\Signatures\Paths::userFontPath($val))) {
            GLPIConfig::setConfigurationValues('plugin_signatures', [$key => $val]);
         }
      }
   }

   if (isset($files['font_upload']) && is_uploaded_file($files['font_upload']['tmp_name'])) {
      $tmp      = $files['font_upload']['tmp_name'];
      $origName = $files['font_upload']['name'] ?? '';
      $size     = $files['font_upload']['size'] ?? 0;

      if ($size > $fontMaxSize) {
         Session::addMessageAfterRedirect(__('Font file too large (Max. 2 MB)', 'signatures'), false, ERROR);
         return new \Symfony\Component\HttpFoundation\RedirectResponse($self . '?error=1&tab=fonts#tab-fonts');
      }
      $safeName = \GlpiPlugin\Signatures\Signature::sanitizeFontFilename($origName);
      if ($safeName === null) {
         Session::addMessageAfterRedirect(__('Invalid font file. Only TTF and OTF files are accepted.', 'signatures'), false, ERROR);
         return new \Symfony\Component\HttpFoundation\RedirectResponse($self . '?error=1&tab=fonts#tab-fonts');
      }
      if (!\GlpiPlugin\Signatures\Signature::validateFontFile($tmp, pathinfo($safeName, PATHINFO_EXTENSION))) {
         Session::addMessageAfterRedirect(__('Invalid font file. Only TTF and OTF files are accepted.', 'signatures'), false, ERROR);
         return new \Symfony\Component\HttpFoundation\RedirectResponse($self . '?error=1&tab=fonts#tab-fonts');
      }
      $dest = \GlpiPlugin\Signatures\Paths::userFontPath($safeName);
      if (!move_uploaded_file($tmp, $dest)) {
         Session::addMessageAfterRedirect(__('Could not save the uploaded font.', 'signatures'), false, ERROR);
         return new \Symfony\Component\HttpFoundation\RedirectResponse($self . '?error=1&tab=fonts#tab-fonts');
      }
      @chmod($dest, 0644);
      Session::addMessageAfterRedirect(
         sprintf(__('Font "%s" uploaded successfully.', 'signatures'), $safeName),
         false,
         INFO
      );
      \GlpiPlugin\Signatures\Config::invalidate();
      return new \Symfony\Component\HttpFoundation\RedirectResponse($self . '?saved=1&tab=fonts#tab-fonts');
   }

   if (isset($post['upload_font_action'])) {
      Session::addMessageAfterRedirect(__('No font file selected.', 'signatures'), false, WARNING);
      return new \Symfony\Component\HttpFoundation\RedirectResponse($self . '?info=1&tab=fonts#tab-fonts');
   }

   // Position/visibility fields are part of the single settings form, but must
   // only be persisted when the position editor was actually changed.
   // Otherwise a save from another tab submits the hidden checkbox fallbacks
   // (0) and silently resets all field visibility settings.
   $positionsDirty = (($post['positions_dirty'] ?? '') === '1');
   if ($positionsDirty) {
      $posToSave = [];
      foreach (array_keys(plugin_signatures_getDefaults()) as $_pk) {
         if (!preg_match('/^sig_b\d+_\w+_(x|y|size)$/', $_pk)) continue;
         if (!isset($post[$_pk]) || $post[$_pk] === '') continue;
         $val = (int)$post[$_pk];
         $val = str_ends_with($_pk, '_size')
            ? max(1, min(200, $val))
            : max(0, min(9999, $val));
         $posToSave[$_pk] = (string)$val;
      }
      if (!empty($posToSave)) {
         GLPIConfig::setConfigurationValues('plugin_signatures', $posToSave);
      }

      $_enabledFieldsMap = [
         'b1' => ['nombre','titulo','email','mobile','tel','ext','facebook','web','x','linkedin','instagram','snapchat','tiktok','qr'],
         'b2' => ['nombre','titulo','email','tel','ext','facebook','web','x','linkedin','instagram','snapchat','tiktok'],
      ];
      $_enabledToSave = [];
      foreach ($_enabledFieldsMap as $_eBase => $_eFields) {
         foreach ($_eFields as $_eFid) {
            $k = "sig_{$_eBase}_{$_eFid}_enabled";
            $_enabledToSave[$k] = ((string)($post[$k] ?? '0')) === '1' ? '1' : '0';
         }
      }
      GLPIConfig::setConfigurationValues('plugin_signatures', $_enabledToSave);
   }

   \GlpiPlugin\Signatures\Config::invalidate();
   Session::addMessageAfterRedirect(__('Configuration saved successfully', 'signatures'), false, INFO);
   return new \Symfony\Component\HttpFoundation\RedirectResponse($self . '?saved=1&tab=' . $activeTab . '#tab-' . $activeTab);
}

/* ========================== DATA ========================== */

$config        = \GlpiPlugin\Signatures\Config::getAll();
$currentPluginVersion = plugin_version_signatures()['version'] ?? PLUGIN_SIGNATURES_VERSION;
$latestReleaseVersion = \GlpiPlugin\Signatures\Config::getLatestReleaseVersion();
$updateAvailable = $latestReleaseVersion !== null && version_compare($latestReleaseVersion, $currentPluginVersion, '>');
$facebookPage  = $config['facebook_page']       ?? '';
$xPage         = $config['x_page']              ?? '';
$linkedinPage  = $config['linkedin_page']        ?? '';
$instagramPage = $config['instagram_page']       ?? '';
$snapchatPage  = $config['snapchat_page']        ?? '';
$tiktokPage    = $config['tiktok_page']          ?? '';
$countryCode   = $config['whatsapp_country_code'] ?? '';
$emailSubject  = $config['email_subject']        ?? '';
$emailBody     = $config['email_body']           ?? '';
$emailFooter   = $config['email_footer']         ?? '';

// Test email button state
$_testUrl   = \GlpiPlugin\Signatures\Paths::sendUrl();
$_coreCfg   = GLPIConfig::getConfigurationValues('core');
$_mailOk    = ($_coreCfg['use_notifications']    ?? 0) == 1
           && ($_coreCfg['notifications_mailing'] ?? 0) == 1;
$_hasConfig = !empty(trim($emailSubject)) && !empty(trim($emailBody));

if (!$_mailOk) {
   $_btnTooltip = __('GLPI mail server not configured', 'signatures');
} elseif (!$_hasConfig) {
   $_btnTooltip = __('Configure the email subject and body first', 'signatures');
} else {
   $_btnTooltip = __('Send a test email to your registered GLPI address', 'signatures');
}
$_testCsrfToken = Session::getNewCSRFToken();

// Position editor: admin user data for sample text
$_adminId   = (int)Session::getLoginUserID();
$_adminUser = new User();
$_adminUser->getFromDB($_adminId);

$_uName   = $_adminUser->getFriendlyName() ?: __('First Last', 'signatures');
$_uEmail  = __('email@company.com', 'signatures');
$_uMobile = $_adminUser->fields['mobile'] ?? '';
$_uPhone  = $_adminUser->fields['phone']  ?? '';
$_uPhone2 = $_adminUser->fields['phone2'] ?? '';

$_uEmails = (new UserEmail())->find(['users_id' => $_adminId, 'is_default' => 1], [], 1);
if (!empty($_uEmails)) {
   $_row    = reset($_uEmails);
   $_uEmail = $_row['email'] ?? $_uEmail;
}
if (empty($_uMobile)) { $_uMobile = __('555 123 4567', 'signatures'); }

$_uTitulo = __('Not specified', 'signatures');
if (!empty($_adminUser->fields['usertitles_id'])) {
   $_uTitulo = Dropdown::getDropdownName('glpi_usertitles', (int)$_adminUser->fields['usertitles_id']);
}

$_entityPos = new Entity();
$_phoneEnt  = '';
$_web       = '';
if ($_entityPos->getFromDB((int)($_adminUser->fields['entities_id'] ?? 0))) {
   $_phoneEnt = (string)$_entityPos->getField('phonenumber');
   $_web      = (string)$_entityPos->getField('website');
}
if (empty($_phoneEnt)) { $_phoneEnt = __('555 987 6543', 'signatures'); }
if (empty($_web))      { $_web = __('www.company.com', 'signatures'); }

$_extraLabel = '';
$_extraPhone = '';
if ($_uPhone2 !== '')    { $_extraLabel = __('Office: ', 'signatures'); $_extraPhone = $_uPhone2; }
elseif ($_uPhone !== '') { $_extraLabel = __('Ext: ', 'signatures');    $_extraPhone = $_uPhone; }
if (empty($_extraPhone)) { $_extraLabel = __('Ext: ', 'signatures');    $_extraPhone = '123'; }

// Font URLs for editor @font-face
$_pluginWebDir     = \GlpiPlugin\Signatures\Paths::webDir();
$_fontNameFile     = trim($config['font_name'] ?? '');
$_fontBodyFile     = trim($config['font_body'] ?? '');

$_resolveFontUrl = static function(string $filename, string $builtinFile) use ($_pluginWebDir): string {
   if ($filename !== '') {
      if ($filename === 'AvenirBlack.ttf' || $filename === 'AvenirRoman.ttf') {
         return $_pluginWebDir . '/fonts/' . $filename;
      }
      if (is_readable(\GlpiPlugin\Signatures\Paths::userFontPath($filename))) {
         return \GlpiPlugin\Signatures\Paths::userFontUrl($filename);
      }
   }
   return $_pluginWebDir . '/fonts/' . $builtinFile;
};

$_fontBlackUrl = $_resolveFontUrl($_fontNameFile, 'AvenirBlack.ttf');
$_fontRomanUrl = $_resolveFontUrl($_fontBodyFile, 'AvenirRoman.ttf');

// Position reader
$_c   = \GlpiPlugin\Signatures\Config::getAll();
$_D   = plugin_signatures_getDefaults();
$_pos = static function (string $key) use ($_c, $_D): int {
   return (int)(($_c[$key] ?? '') !== '' ? $_c[$key] : ($_D[$key] ?? 0));
};

// Field arrays
$_fieldsB1 = [
   ['nombre',   __('Name', 'signatures'),         $_pos('sig_b1_nombre_x'),   $_pos('sig_b1_nombre_y'),   $_pos('sig_b1_nombre_size'),   'black', 'white', $_uName],
   ['titulo',   __('Title', 'signatures'),        $_pos('sig_b1_titulo_x'),   $_pos('sig_b1_titulo_y'),   $_pos('sig_b1_titulo_size'),   'black', 'white', $_uTitulo],
   ['email',    __('Email', 'signatures'),         $_pos('sig_b1_email_x'),    $_pos('sig_b1_email_y'),    $_pos('sig_b1_email_size'),    'roman', 'black', $_uEmail],
   ['mobile',   __('Mobile', 'signatures'),        $_pos('sig_b1_mobile_x'),   $_pos('sig_b1_mobile_y'),   $_pos('sig_b1_mobile_size'),   'roman', 'black', $_uMobile],
   ['tel',      __('Entity phone', 'signatures'),  $_pos('sig_b1_tel_x'),      $_pos('sig_b1_tel_y'),      $_pos('sig_b1_tel_size'),      'roman', 'black', $_phoneEnt],
   ['ext',      __('Ext/Office', 'signatures'),    $_pos('sig_b1_ext_x'),      $_pos('sig_b1_ext_y'),      $_pos('sig_b1_ext_size'),      'roman', 'black', $_extraLabel . $_extraPhone],
   ['facebook', __('Facebook', 'signatures'),      $_pos('sig_b1_facebook_x'), $_pos('sig_b1_facebook_y'), $_pos('sig_b1_facebook_size'), 'roman', 'black', $facebookPage ?: 'cyalimentos'],
   ['web',      __('Website', 'signatures'),       $_pos('sig_b1_web_x'),      $_pos('sig_b1_web_y'),      $_pos('sig_b1_web_size'),      'roman', 'black', $_web],
   ['x',        __('X / Twitter', 'signatures'),   $_pos('sig_b1_x_x'),        $_pos('sig_b1_x_y'),        $_pos('sig_b1_x_size'),        'roman', 'black', $xPage ?: '@empresa'],
   ['linkedin', __('LinkedIn', 'signatures'),      $_pos('sig_b1_linkedin_x'), $_pos('sig_b1_linkedin_y'), $_pos('sig_b1_linkedin_size'), 'roman', 'black', $linkedinPage ?: 'empresa'],
   ['instagram',__('Instagram', 'signatures'),     $_pos('sig_b1_instagram_x'),$_pos('sig_b1_instagram_y'),$_pos('sig_b1_instagram_size'),'roman', 'black', $instagramPage ?: '@empresa'],
   ['snapchat', __('Snapchat', 'signatures'),      $_pos('sig_b1_snapchat_x'), $_pos('sig_b1_snapchat_y'), $_pos('sig_b1_snapchat_size'), 'roman', 'black', $snapchatPage ?: 'empresa'],
   ['tiktok',   __('TikTok', 'signatures'),        $_pos('sig_b1_tiktok_x'),   $_pos('sig_b1_tiktok_y'),   $_pos('sig_b1_tiktok_size'),   'roman', 'black', $tiktokPage ?: '@empresa'],
   ['qr',       __('WhatsApp QR', 'signatures'),   $_pos('sig_b1_qr_x'),       $_pos('sig_b1_qr_y'),       0,                             'roman', 'black', '▣ QR'],
];

$_fieldsB2 = [
   ['nombre',   __('Name', 'signatures'),         $_pos('sig_b2_nombre_x'),   $_pos('sig_b2_nombre_y'),   $_pos('sig_b2_nombre_size'),   'black', 'white', $_uName],
   ['titulo',   __('Title', 'signatures'),        $_pos('sig_b2_titulo_x'),   $_pos('sig_b2_titulo_y'),   $_pos('sig_b2_titulo_size'),   'black', 'white', $_uTitulo],
   ['email',    __('Email', 'signatures'),         $_pos('sig_b2_email_x'),    $_pos('sig_b2_email_y'),    $_pos('sig_b2_email_size'),    'roman', 'black', $_uEmail],
   ['tel',      __('Entity phone', 'signatures'),  $_pos('sig_b2_tel_x'),      $_pos('sig_b2_tel_y'),      $_pos('sig_b2_tel_size'),      'roman', 'black', $_phoneEnt],
   ['ext',      __('Ext/Office', 'signatures'),    $_pos('sig_b2_ext_x'),      $_pos('sig_b2_ext_y'),      $_pos('sig_b2_ext_size'),      'roman', 'black', $_extraLabel . $_extraPhone],
   ['facebook', __('Facebook', 'signatures'),      $_pos('sig_b2_facebook_x'), $_pos('sig_b2_facebook_y'), $_pos('sig_b2_facebook_size'), 'roman', 'black', $facebookPage ?: 'cyalimentos'],
   ['web',      __('Website', 'signatures'),       $_pos('sig_b2_web_x'),      $_pos('sig_b2_web_y'),      $_pos('sig_b2_web_size'),      'roman', 'black', $_web],
   ['x',        __('X / Twitter', 'signatures'),   $_pos('sig_b2_x_x'),        $_pos('sig_b2_x_y'),        $_pos('sig_b2_x_size'),        'roman', 'black', $xPage ?: '@empresa'],
   ['linkedin', __('LinkedIn', 'signatures'),      $_pos('sig_b2_linkedin_x'), $_pos('sig_b2_linkedin_y'), $_pos('sig_b2_linkedin_size'), 'roman', 'black', $linkedinPage ?: 'empresa'],
   ['instagram',__('Instagram', 'signatures'),     $_pos('sig_b2_instagram_x'),$_pos('sig_b2_instagram_y'),$_pos('sig_b2_instagram_size'),'roman', 'black', $instagramPage ?: '@empresa'],
   ['snapchat', __('Snapchat', 'signatures'),      $_pos('sig_b2_snapchat_x'), $_pos('sig_b2_snapchat_y'), $_pos('sig_b2_snapchat_size'), 'roman', 'black', $snapchatPage ?: 'empresa'],
   ['tiktok',   __('TikTok', 'signatures'),        $_pos('sig_b2_tiktok_x'),   $_pos('sig_b2_tiktok_y'),   $_pos('sig_b2_tiktok_size'),   'roman', 'black', $tiktokPage ?: '@empresa'],
];

// Format field arrays for Twig
$ASCENT_FACTOR = 0.72;
$formatFields  = static function (array $fields, string $base, array $cfg) use ($ASCENT_FACTOR): array {
   return array_map(static function (array $f) use ($base, $cfg, $ASCENT_FACTOR): array {
      [$id, $label, $x, $y, $size, $font, $color, $sample] = $f;
      $isQr    = ($id === 'qr');
      $qrMod   = $isQr ? max(1, min(10, (int)(($cfg['sig_b1_qr_size'] ?? '') !== '' ? $cfg['sig_b1_qr_size'] : 3))) : 0;
      return [
         'id'        => $id,
         'label'     => $label,
         'x'         => $x,
         'y'         => $y,
         'size'      => $size,
         'font_css'  => $font === 'black' ? 'AvenirBlack' : 'AvenirRoman',
         'color_css' => $color === 'white' ? '#fff' : '#000',
         'sample'    => $sample,
         'enabled'   => ($cfg["sig_{$base}_{$id}_enabled"] ?? '1') !== '0',
         'is_qr'     => $isQr,
         'qr_module' => $qrMod,
         'qr_px'     => $isQr ? $qrMod * 29 : 0,
         'css_left'  => $x,
         'css_top'   => $isQr ? $y : max(0, (int)round($y - $size * $ASCENT_FACTOR)),
      ];
   }, $fields);
};

// Fonts tab
$userFontsMap = \GlpiPlugin\Signatures\Paths::listUserFontsWithNames();
$currentName  = $config['font_name'] ?? '';
$currentBody  = $config['font_body'] ?? '';

$fontDeleteTokens = [];
foreach (array_keys($userFontsMap) as $fname) {
   $fontDeleteTokens[$fname] = Session::getNewCSRFToken();
}

// Defaults for JS reset
$defaults = [
   'b1' => [
      'nombre'    => ['x' => 20,  'y' => 75,  'size' => 40],
      'titulo'    => ['x' => 20,  'y' => 104, 'size' => 11],
      'email'     => ['x' => 63,  'y' => 138, 'size' => 11],
      'mobile'    => ['x' => 63,  'y' => 161, 'size' => 11],
      'tel'       => ['x' => 185, 'y' => 161, 'size' => 11],
      'ext'       => ['x' => 283, 'y' => 161, 'size' => 11],
      'facebook'  => ['x' => 63,  'y' => 183, 'size' => 11],
      'web'       => ['x' => 185, 'y' => 183, 'size' => 11],
      'x'         => ['x' => 63,  'y' => 205, 'size' => 11],
      'linkedin'  => ['x' => 185, 'y' => 205, 'size' => 11],
      'instagram' => ['x' => 320, 'y' => 205, 'size' => 11],
      'snapchat'  => ['x' => 450, 'y' => 205, 'size' => 11],
      'tiktok'    => ['x' => 63,  'y' => 227, 'size' => 11],
      'qr'        => ['x' => 560, 'y' => 130, 'size' => 3],
   ],
   'b2' => [
      'nombre'    => ['x' => 20,  'y' => 75,  'size' => 40],
      'titulo'    => ['x' => 20,  'y' => 104, 'size' => 11],
      'email'     => ['x' => 63,  'y' => 138, 'size' => 11],
      'tel'       => ['x' => 63,  'y' => 161, 'size' => 11],
      'ext'       => ['x' => 160, 'y' => 161, 'size' => 11],
      'facebook'  => ['x' => 63,  'y' => 183, 'size' => 11],
      'web'       => ['x' => 185, 'y' => 183, 'size' => 11],
      'x'         => ['x' => 63,  'y' => 205, 'size' => 11],
      'linkedin'  => ['x' => 185, 'y' => 205, 'size' => 11],
      'instagram' => ['x' => 320, 'y' => 205, 'size' => 11],
      'snapchat'  => ['x' => 450, 'y' => 205, 'size' => 11],
      'tiktok'    => ['x' => 63,  'y' => 227, 'size' => 11],
   ],
];

/* ========================== RENDER ========================== */

Html::header(__('Email Signature', 'signatures'), $self, 'config', 'plugins');

\GlpiPlugin\Signatures\Renderer::display(
   'config_form.html.twig',
   [
      // Form meta
      'self'             => $self,
      'active_tab'       => $activeTab,
      'csrf_token'       => Session::getNewCSRFToken(),
      'test_csrf_token'  => $_testCsrfToken,
      'test_url'         => $_testUrl,
      'current_plugin_version' => $currentPluginVersion,
      'latest_release_version' => $latestReleaseVersion,
      'update_available'       => $updateAvailable,
      'releases_url'           => 'https://github.com/monta990/signatures/releases',

      // Social
      'facebook_page'    => $facebookPage,
      'x_page'           => $xPage,
      'linkedin_page'    => $linkedinPage,
      'instagram_page'   => $instagramPage,
      'snapchat_page'    => $snapchatPage,
      'tiktok_page'      => $tiktokPage,
      'country_code'     => $countryCode,

      // Email
      'email_subject'    => $emailSubject,
      'email_body'       => $emailBody,
      'email_footer'     => $emailFooter,

      // Test email button
      'mail_ok'          => $_mailOk,
      'has_email_config' => $_hasConfig,
      'btn_tooltip'      => $_btnTooltip,

      // Template files
      'hasbase1'         => $hasbase1,
      'hasbase2'         => $hasbase2,
      'base1_url'        => $hasbase1 ? \GlpiPlugin\Signatures\Paths::base1Url() . '&t=' . filemtime($base1File) : '',
      'base2_url'        => $hasbase2 ? \GlpiPlugin\Signatures\Paths::base2Url() . '&t=' . filemtime($base2File) : '',
      'raw_base1_url'    => $hasbase1 ? \GlpiPlugin\Signatures\Paths::base1Url() : '',
      'raw_base2_url'    => $hasbase2 ? \GlpiPlugin\Signatures\Paths::base2Url() : '',
      'cache_bust1'      => $hasbase1 ? filemtime($base1File) : 0,
      'cache_bust2'      => $hasbase2 ? filemtime($base2File) : 0,
      'csrf1'            => $hasbase1 ? Session::getNewCSRFToken() : '',
      'csrf2'            => $hasbase2 ? Session::getNewCSRFToken() : '',

      // Position editor
      'fields_b1'        => $formatFields($_fieldsB1, 'b1', $_c),
      'fields_b2'        => $formatFields($_fieldsB2, 'b2', $_c),

      // Fonts tab
      'user_fonts_map'      => $userFontsMap,
      'current_name'        => $currentName,
      'current_body'        => $currentBody,
      'builtin_name'        => \GlpiPlugin\Signatures\Paths::BUILTIN_FONT_NAME,
      'builtin_body'        => \GlpiPlugin\Signatures\Paths::BUILTIN_FONT_BODY,
      'font_delete_tokens'  => $fontDeleteTokens,

      // JS config
      'plugin_web_dir' => $_pluginWebDir,
      'cfg_js_json'    => json_encode([
         'fontBlackUrl' => $_fontBlackUrl,
         'fontRomanUrl' => $_fontRomanUrl,
         'ascent'       => 0.72,
         'defaults'     => $defaults,
         'activeTab'    => $activeTab,
         'i18n'         => [
            'confirmReset'      => __('Reset all positions to default values? This action cannot be undone until you save.', 'signatures'),
            'confirmDelete'     => __('Delete?', 'signatures'),
            'unsavedChanges'    => __('Unsaved changes', 'signatures'),
            'onlyPng'           => __('Only PNG files are allowed', 'signatures'),
            'formatPlaceholder' => __('text', 'signatures'),
         ],
      ], JSON_UNESCAPED_UNICODE),
   ]
);

Html::footer();
return new \Symfony\Component\HttpFoundation\Response(ob_get_clean() ?: '');
   }
}
