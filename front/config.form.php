<?php
declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
   die('Direct access not allowed');
}

Session::checkRight('config', UPDATE);

global $CFG_GLPI;

$self = ($CFG_GLPI['root_doc'] ?? '') . '/plugins/signatures/front/config.form.php';

$_sigRedirect = static function (string $url): never {
   while (ob_get_level() > 0) {
      ob_end_clean();
   }
   header('Location: ' . $url, true, 302);
   exit();
};

$_validTabs = ['general','cel','nocel','positions','fonts'];
$activeTab  = in_array($_POST['active_tab'] ?? '', $_validTabs, true)
   ? $_POST['active_tab']
   : (in_array($_GET['tab'] ?? '', $_validTabs, true) ? $_GET['tab'] : 'general');

/* ========================== CONFIG ========================== */

$maxSize     = 300 * 1024;
$allowedMime = ['image/png'];

$fontMaxSize = 2 * 1024 * 1024;

$baseDir   = PluginSignaturesPaths::filesDir();
$base1File = PluginSignaturesPaths::base1Path();
$base2File = PluginSignaturesPaths::base2Path();

$userFontsDir = PluginSignaturesPaths::userFontsDir();

$hasbase1  = is_readable($base1File);
$hasbase2  = is_readable($base2File);

if (!is_dir($baseDir)) {
   mkdir($baseDir, 0755, true);
}

if (!is_dir($userFontsDir)) {
   mkdir($userFontsDir, 0755, true);
}

/* ========================== DELETE ========================== */

if (isset($_POST['delete_base1']) && $hasbase1) {
   unlink($base1File);
   Session::addMessageAfterRedirect(__('Mobile template deleted', 'signatures'), false, INFO);
   $_sigRedirect($self . '?deleted=1');
}

if (isset($_POST['delete_base2']) && $hasbase2) {
   unlink($base2File);
   Session::addMessageAfterRedirect(__('No-mobile template deleted', 'signatures'), false, INFO);
   $_sigRedirect($self . '?deleted=1');
}

if (isset($_POST['delete_font'])) {
   $fontToDelete = basename(trim($_POST['delete_font'] ?? ''));
   $ext          = strtolower(pathinfo($fontToDelete, PATHINFO_EXTENSION));

   if ($fontToDelete !== '' && in_array($ext, ['ttf', 'otf'], true)) {
      $fontPath = PluginSignaturesPaths::userFontPath($fontToDelete);
      if (is_file($fontPath)) {
         $cfg = PluginSignaturesConfig::getAll();
         foreach (['font_name', 'font_body'] as $key) {
            if (($cfg[$key] ?? '') === $fontToDelete) {
               Config::setConfigurationValues('plugin_signatures', [$key => '']);
            }
         }
         PluginSignaturesConfig::invalidate();
         unlink($fontPath);
         Session::addMessageAfterRedirect(__('Font deleted', 'signatures'), false, INFO);
      }
   }
   $_sigRedirect($self . '?deleted=1&tab=fonts#tab-fonts');
}

/* ========================== SAVE ========================== */

if (isset($_POST['save'])) {

   if (isset($_POST['facebook_page'])) {
      Config::setConfigurationValues(
         'plugin_signatures',
         ['facebook_page' => trim($_POST['facebook_page'])]
      );
   }

   foreach (['x_page', 'linkedin_page', 'instagram_page', 'snapchat_page', 'tiktok_page'] as $_snKey) {
      if (isset($_POST[$_snKey])) {
         Config::setConfigurationValues('plugin_signatures', [$_snKey => trim($_POST[$_snKey])]);
      }
   }

   if (isset($_POST['whatsapp_country_code'])) {
      Config::setConfigurationValues(
         'plugin_signatures',
         ['whatsapp_country_code' => preg_replace('/[^0-9]/', '', trim($_POST['whatsapp_country_code']))]
      );
   }

   if (isset($_POST['email_subject'])) {
      Config::setConfigurationValues('plugin_signatures', ['email_subject' => trim($_POST['email_subject'])]);
   }
   if (isset($_POST['email_body'])) {
      Config::setConfigurationValues('plugin_signatures', ['email_body' => trim($_POST['email_body'])]);
   }
   if (isset($_POST['email_footer'])) {
      Config::setConfigurationValues('plugin_signatures', ['email_footer' => trim($_POST['email_footer'])]);
   }

   foreach (['base1' => $base1File, 'base2' => $base2File] as $field => $dest) {
      if (!isset($_FILES[$field]) || !is_uploaded_file($_FILES[$field]['tmp_name'])) {
         continue;
      }
      $tmp  = $_FILES[$field]['tmp_name'];
      $size = $_FILES[$field]['size'];
      $finfo = finfo_open(FILEINFO_MIME_TYPE);
      $mime  = finfo_file($finfo, $tmp);
      finfo_close($finfo);

      if ($size > $maxSize) {
         Session::addMessageAfterRedirect(__('File too large (Max. 300 KB)', 'signatures'), false, ERROR);
         $_sigRedirect($self . '?error=1');
      }
      if (!in_array($mime, $allowedMime, true)) {
         Session::addMessageAfterRedirect(__('Invalid format, only PNG files are allowed', 'signatures'), false, ERROR);
         $_sigRedirect($self . '?error=1');
      }
      move_uploaded_file($tmp, $dest);
      chmod($dest, 0644);
   }

   $_builtinFonts = [PluginSignaturesPaths::BUILTIN_FONT_NAME, PluginSignaturesPaths::BUILTIN_FONT_BODY];
   foreach (['font_name', 'font_body'] as $key) {
      if (isset($_POST[$key])) {
         $val = basename(trim($_POST[$key]));
         if ($val === '' || in_array($val, $_builtinFonts, true) || is_readable(PluginSignaturesPaths::userFontPath($val))) {
            Config::setConfigurationValues('plugin_signatures', [$key => $val]);
         }
      }
   }

   if (isset($_FILES['font_upload']) && is_uploaded_file($_FILES['font_upload']['tmp_name'])) {
      $tmp      = $_FILES['font_upload']['tmp_name'];
      $origName = $_FILES['font_upload']['name'] ?? '';
      $size     = $_FILES['font_upload']['size'] ?? 0;

      if ($size > $fontMaxSize) {
         Session::addMessageAfterRedirect(__('Font file too large (Max. 2 MB)', 'signatures'), false, ERROR);
         $_sigRedirect($self . '?error=1&tab=fonts#tab-fonts');
      }
      $safeName = PluginSignaturesSignature::sanitizeFontFilename($origName);
      if ($safeName === null) {
         Session::addMessageAfterRedirect(__('Invalid font file. Only TTF and OTF files are accepted.', 'signatures'), false, ERROR);
         $_sigRedirect($self . '?error=1&tab=fonts#tab-fonts');
      }
      if (!PluginSignaturesSignature::validateFontFile($tmp)) {
         Session::addMessageAfterRedirect(__('Invalid font file. Only TTF and OTF files are accepted.', 'signatures'), false, ERROR);
         $_sigRedirect($self . '?error=1&tab=fonts#tab-fonts');
      }
      $dest = PluginSignaturesPaths::userFontPath($safeName);
      move_uploaded_file($tmp, $dest);
      chmod($dest, 0644);
      Session::addMessageAfterRedirect(
         sprintf(__('Font "%s" uploaded successfully.', 'signatures'), $safeName),
         false,
         INFO
      );
      PluginSignaturesConfig::invalidate();
      $_sigRedirect($self . '?saved=1&tab=fonts#tab-fonts');
   }

   if (isset($_POST['upload_font_action'])) {
      Session::addMessageAfterRedirect(__('No font file selected.', 'signatures'), false, WARNING);
      $_sigRedirect($self . '?info=1&tab=fonts#tab-fonts');
   }

   $posToSave = [];
   foreach (array_keys(plugin_signatures_getDefaults()) as $_pk) {
      if (!preg_match('/^sig_b\d+_\w+_(x|y|size)$/', $_pk)) continue;
      if (!isset($_POST[$_pk]) || $_POST[$_pk] === '') continue;
      $val = (int)$_POST[$_pk];
      $val = str_ends_with($_pk, '_size')
         ? max(1, min(200, $val))
         : max(0, min(9999, $val));
      $posToSave[$_pk] = (string)$val;
   }
   if (!empty($posToSave)) {
      Config::setConfigurationValues('plugin_signatures', $posToSave);
   }

   $_enabledFieldsMap = [
      'b1' => ['nombre','titulo','email','mobile','tel','ext','facebook','web','x','linkedin','instagram','snapchat','tiktok','qr'],
      'b2' => ['nombre','titulo','email','tel','ext','facebook','web','x','linkedin','instagram','snapchat','tiktok'],
   ];
   $_enabledToSave = [];
   foreach ($_enabledFieldsMap as $_eBase => $_eFields) {
      foreach ($_eFields as $_eFid) {
         $k = "sig_{$_eBase}_{$_eFid}_enabled";
         $_enabledToSave[$k] = isset($_POST[$k]) ? '1' : '0';
      }
   }
   Config::setConfigurationValues('plugin_signatures', $_enabledToSave);

   PluginSignaturesConfig::invalidate();
   Session::addMessageAfterRedirect(__('Configuration saved successfully', 'signatures'), false, INFO);
   $_sigRedirect($self . '?saved=1&tab=' . $activeTab . '#tab-' . $activeTab);
}

/* ========================== DATA ========================== */

$config        = PluginSignaturesConfig::getAll();
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
$_testUrl   = ($CFG_GLPI['root_doc'] ?? '') . '/plugins/signatures/front/send.php';
$_coreCfg   = Config::getConfigurationValues('core');
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
$_pluginWebDir     = ($CFG_GLPI['root_doc'] ?? '') . '/plugins/signatures';
$_fontNameFile     = trim($config['font_name'] ?? '');
$_fontBodyFile     = trim($config['font_body'] ?? '');

$_resolveFontUrl = static function(string $filename, string $builtinFile) use ($_pluginWebDir): string {
   if ($filename !== '') {
      if ($filename === 'AvenirBlack.ttf' || $filename === 'AvenirRoman.ttf') {
         return $_pluginWebDir . '/fonts/' . $filename;
      }
      if (is_readable(PluginSignaturesPaths::userFontPath($filename))) {
         return PluginSignaturesPaths::userFontUrl($filename);
      }
   }
   return $_pluginWebDir . '/fonts/' . $builtinFile;
};

$_fontBlackUrl = $_resolveFontUrl($_fontNameFile, 'AvenirBlack.ttf');
$_fontRomanUrl = $_resolveFontUrl($_fontBodyFile, 'AvenirRoman.ttf');

// Position reader
$_c   = PluginSignaturesConfig::getAll();
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
      $isQr = ($id === 'qr');
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
         'css_left'  => $x,
         'css_top'   => $isQr ? $y : max(0, (int)round($y - $size * $ASCENT_FACTOR)),
      ];
   }, $fields);
};

// Fonts tab
$userFontsMap = PluginSignaturesPaths::listUserFontsWithNames();
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
      'qr'        => ['x' => 560, 'y' => 130],
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

PluginSignaturesRenderer::display(
   'config_form.html.twig',
   [
      // Form meta
      'self'             => $self,
      'active_tab'       => $activeTab,
      'csrf_token'       => Session::getNewCSRFToken(),
      'test_csrf_token'  => $_testCsrfToken,
      'test_url'         => $_testUrl,

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
      'base1_url'        => $hasbase1 ? PluginSignaturesPaths::base1Url() . '&t=' . filemtime($base1File) : '',
      'base2_url'        => $hasbase2 ? PluginSignaturesPaths::base2Url() . '&t=' . filemtime($base2File) : '',
      'raw_base1_url'    => $hasbase1 ? PluginSignaturesPaths::base1Url() : '',
      'raw_base2_url'    => $hasbase2 ? PluginSignaturesPaths::base2Url() : '',
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
      'builtin_name'        => PluginSignaturesPaths::BUILTIN_FONT_NAME,
      'builtin_body'        => PluginSignaturesPaths::BUILTIN_FONT_BODY,
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
            'unsavedChanges'    => __('Unsaved changes', 'signatures'),
            'onlyPng'           => __('Only PNG files are allowed', 'signatures'),
            'formatPlaceholder' => __('text', 'signatures'),
         ],
      ], JSON_UNESCAPED_UNICODE),
   ]
);

Html::footer();
