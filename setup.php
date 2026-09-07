<?php

if (!defined('GLPI_ROOT')) {
   die('Direct access not allowed');
}

use Glpi\Plugin\Hooks;
use GlpiPlugin\Signatures\UserTab;

define('PLUGIN_SIGNATURES_VERSION', '1.8.1');
define('PLUGIN_SIGNATURES_MIN_GLPI', '11.0');
define('PLUGIN_SIGNATURES_MAX_GLPI', '12.99');

function plugin_init_signatures(): void {
   global $PLUGIN_HOOKS;

   $PLUGIN_HOOKS[Hooks::CONFIG_PAGE]['signatures'] = 'config';
   $PLUGIN_HOOKS['add_javascript']['signatures'] = ['js/signatures-user.js', 'js/signatures-config.js'];

   Plugin::registerClass(
      UserTab::class,
      ['addtabon' => ['User']]
   );
}

function plugin_version_signatures(): array {
   return [
      'name'         => 'Email Signatures',
      'version'      => PLUGIN_SIGNATURES_VERSION,
      '1.8.1'       => 'Edwin Elias Alvarez',
      'license'      => 'GPLv3+',
      'homepage'     => 'https://github.com/monta990/signatures',
      'minphpversion' => '8.2',
      'requirements' => [
         'glpi' => [
            'min' => PLUGIN_SIGNATURES_MIN_GLPI,
            'max' => PLUGIN_SIGNATURES_MAX_GLPI,
         ],
      ]
   ];
}

/**
 * Valores por defecto para todas las claves de configuración del plugin.
 * Se usa en install() para inicializar y puede usarse para reset.
 */
function plugin_signatures_getDefaults(): array {
   return [
      // General
      'facebook_page'           => '',
      'x_page'                  => '',
      'linkedin_page'           => '',
      'instagram_page'          => '',
      'snapchat_page'           => '',
      'tiktok_page'             => '',
      'whatsapp_country_code'   => '52',
      'email_subject'           => '',
      'email_body'              => '',
      'email_footer'            => '',
      // ── Custom fonts (empty = use built-in Avenir) ─────────────────────────
      'font_name'               => '',
      'font_body'               => '',
      // ── Posiciones plantilla CON celular (base1) ──────────────────────
      'sig_b1_nombre_x'         => 20,  'sig_b1_nombre_y'   => 75,  'sig_b1_nombre_size'   => 40,
      'sig_b1_titulo_x'         => 20,  'sig_b1_titulo_y'   => 104, 'sig_b1_titulo_size'   => 11,
      'sig_b1_email_x'          => 63,  'sig_b1_email_y'    => 138, 'sig_b1_email_size'    => 11,
      'sig_b1_mobile_x'         => 63,  'sig_b1_mobile_y'   => 161, 'sig_b1_mobile_size'   => 11,
      'sig_b1_tel_x'            => 185, 'sig_b1_tel_y'      => 161, 'sig_b1_tel_size'      => 11,
      'sig_b1_ext_x'            => 283, 'sig_b1_ext_y'      => 161, 'sig_b1_ext_size'      => 11,
      'sig_b1_facebook_x'        => 63,  'sig_b1_facebook_y'   => 183, 'sig_b1_facebook_size'   => 11,
      'sig_b1_web_x'             => 185, 'sig_b1_web_y'        => 183, 'sig_b1_web_size'        => 11,
      'sig_b1_x_x'               => 63,  'sig_b1_x_y'          => 205, 'sig_b1_x_size'          => 11,
      'sig_b1_linkedin_x'        => 185, 'sig_b1_linkedin_y'   => 205, 'sig_b1_linkedin_size'   => 11,
      'sig_b1_instagram_x'       => 320, 'sig_b1_instagram_y'  => 205, 'sig_b1_instagram_size'  => 11,
      'sig_b1_snapchat_x'        => 450, 'sig_b1_snapchat_y'   => 205, 'sig_b1_snapchat_size'   => 11,
      'sig_b1_tiktok_x'          => 63,  'sig_b1_tiktok_y'     => 227, 'sig_b1_tiktok_size'     => 11,
      'sig_b1_qr_x'              => 560, 'sig_b1_qr_y'         => 130, 'sig_b1_qr_size'  => 3,
      // ── Posiciones plantilla SIN celular (base2) ──────────────────────
      'sig_b2_nombre_x'          => 20,  'sig_b2_nombre_y'     => 75,  'sig_b2_nombre_size'     => 40,
      'sig_b2_titulo_x'          => 20,  'sig_b2_titulo_y'     => 104, 'sig_b2_titulo_size'     => 11,
      'sig_b2_email_x'           => 63,  'sig_b2_email_y'      => 138, 'sig_b2_email_size'      => 11,
      'sig_b2_tel_x'             => 63,  'sig_b2_tel_y'        => 161, 'sig_b2_tel_size'        => 11,
      'sig_b2_ext_x'             => 160, 'sig_b2_ext_y'        => 161, 'sig_b2_ext_size'        => 11,
      'sig_b2_facebook_x'        => 63,  'sig_b2_facebook_y'   => 183, 'sig_b2_facebook_size'   => 11,
      'sig_b2_web_x'             => 185, 'sig_b2_web_y'        => 183, 'sig_b2_web_size'        => 11,
      'sig_b2_x_x'               => 63,  'sig_b2_x_y'          => 205, 'sig_b2_x_size'          => 11,
      'sig_b2_linkedin_x'        => 185, 'sig_b2_linkedin_y'   => 205, 'sig_b2_linkedin_size'   => 11,
      'sig_b2_instagram_x'       => 320, 'sig_b2_instagram_y'  => 205, 'sig_b2_instagram_size'  => 11,
      'sig_b2_snapchat_x'        => 450, 'sig_b2_snapchat_y'   => 205, 'sig_b2_snapchat_size'   => 11,
      'sig_b2_tiktok_x'          => 63,  'sig_b2_tiktok_y'     => 227, 'sig_b2_tiktok_size'     => 11,
      // ── Campos habilitados (1 = visible, 0 = oculto) ──────────────────────
      'sig_b1_nombre_enabled'    => 1,   'sig_b1_titulo_enabled'    => 1,
      'sig_b1_email_enabled'     => 1,   'sig_b1_mobile_enabled'    => 1,
      'sig_b1_tel_enabled'       => 1,   'sig_b1_ext_enabled'       => 1,
      'sig_b1_facebook_enabled'  => 1,   'sig_b1_web_enabled'       => 1,
      'sig_b1_x_enabled'         => 1,   'sig_b1_linkedin_enabled'  => 1,
      'sig_b1_instagram_enabled' => 1,   'sig_b1_snapchat_enabled'  => 1,
      'sig_b1_tiktok_enabled'    => 1,   'sig_b1_qr_enabled'        => 1,
      'sig_b2_nombre_enabled'    => 1,   'sig_b2_titulo_enabled'    => 1,
      'sig_b2_email_enabled'     => 1,   'sig_b2_tel_enabled'       => 1,
      'sig_b2_ext_enabled'       => 1,   'sig_b2_facebook_enabled'  => 1,
      'sig_b2_web_enabled'       => 1,   'sig_b2_x_enabled'         => 1,
      'sig_b2_linkedin_enabled'  => 1,   'sig_b2_instagram_enabled' => 1,
      'sig_b2_snapchat_enabled'  => 1,   'sig_b2_tiktok_enabled'    => 1,
   ];
}


/**
 * Solicita a GLPI la limpieza de sus cachés regenerables después de instalar
 * o actualizar el plugin.
 *
 * La limpieza se difiere al final de la petición actual. Esto es importante
 * porque el contenedor Symfony/Twig de GLPI puede estar siendo utilizado
 * durante la misma petición de instalación/actualización.
 */
function plugin_signatures_clear_cache(): void {
   register_shutdown_function(static function (): void {
         if (!class_exists(\Glpi\Cache\CacheManager::class)) {
            return;
         }

         try {
            (new \Glpi\Cache\CacheManager())->resetAllCaches();
         } catch (\Throwable $e) {
            // Never make plugin installation/update fail because cache cleanup
            // could not be completed.
            if (method_exists('Toolbox', 'logInFile')) {
               \Toolbox::logInFile(
                  'php-errors',
                  sprintf(
                     "Signatures: unable to reset GLPI cache after installation/update: %s\n",
                     $e->getMessage()
                  )
               );
            }
         }
      });
}

function plugin_signatures_install(): bool {
   // GLPI calls this hook both for a first installation and for an upgrade.
   // It must therefore be idempotent: create only missing configuration keys
   // and never overwrite values already configured by the administrator.
   $dir = GLPI_PLUGIN_DOC_DIR . '/signatures/templates';
   if (!is_dir($dir)) {
      mkdir($dir, 0755, true);
   }

   $fontsDir = GLPI_PLUGIN_DOC_DIR . '/signatures/fonts';
   if (!is_dir($fontsDir)) {
      mkdir($fontsDir, 0755, true);
   }

   $existing = \Config::getConfigurationValues('plugin_signatures');
   $defaults = plugin_signatures_getDefaults();

   // Check key existence, not truthiness: an existing "0" must remain "0".
   $missing = array_diff_key($defaults, $existing);

   if (!empty($missing)) {
      \Config::setConfigurationValues('plugin_signatures', $missing);
   }

   plugin_signatures_clear_cache();

   return true;
}

function plugin_signatures_uninstall(): bool {
   \Config::deleteConfigurationValues(
      'plugin_signatures',
      array_keys(plugin_signatures_getDefaults())
   );

   // Remove only the plugin-owned persistent data directory.
   $dataDir = rtrim((string)GLPI_PLUGIN_DOC_DIR, '/\\') . '/signatures';
   $docRoot = rtrim((string)GLPI_PLUGIN_DOC_DIR, '/\\');

   if ($dataDir !== $docRoot . '/signatures' || !is_dir($dataDir) || is_link($dataDir)) {
      return true;
   }

   $iterator = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($dataDir, \FilesystemIterator::SKIP_DOTS),
      \RecursiveIteratorIterator::CHILD_FIRST
   );

   foreach ($iterator as $item) {
      try {
         if ($item->isLink() || $item->isFile()) {
            @unlink($item->getPathname());
         } elseif ($item->isDir()) {
            @rmdir($item->getPathname());
         }
      } catch (\Throwable $e) {
         // Uninstall must not fail because a stale file cannot be removed.
      }
   }

   @rmdir($dataDir);

   return true;
}

function plugin_signatures_check_prerequisites(): bool {

   if (version_compare(PHP_VERSION, '8.2', '<')) {
      Session::addMessageAfterRedirect(
         __('PHP 8.2 or higher is required to install this plugin.', 'signatures'),
         false,
         ERROR
      );
      return false;
   }

   if (!extension_loaded('gd')) {
      Session::addMessageAfterRedirect(
         __('The PHP GD extension is required to install this plugin.', 'signatures'),
         false,
         ERROR
      );
      return false;
   }

   return true;
}

function plugin_signatures_check_config(): bool {
   return true;
}
