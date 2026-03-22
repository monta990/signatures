<?php

if (!defined('GLPI_ROOT')) {
   die('Direct access not allowed');
}

function plugin_init_signatures(): void {
   global $PLUGIN_HOOKS;

   $PLUGIN_HOOKS['csrf_compliant']['signatures'] = true;
   $PLUGIN_HOOKS['config_page']['signatures']    = 'front/config.form.php';

   Plugin::registerClass(
      'PluginSignaturesUser',
      ['addtabon' => ['User']]
   );
}

function plugin_version_signatures(): array {
   return [
      'name'         => 'Email Signatures',
      'version'      => '1.3.4',
      'author'       => 'Edwin Elias Alvarez',
      'license'      => 'GPLv2+',
      'homepage'     => 'https://sontechs.com',
      'requirements' => [
         'glpi' => ['min' => '11.0']
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
      'whatsapp_country_code'   => '52',
      'email_subject'           => '',
      'email_body'              => '',
      'email_footer'            => '',
      // ── Posiciones plantilla CON celular (base1) ──────────────────────
      'sig_b1_nombre_x'         => 20,  'sig_b1_nombre_y'   => 75,  'sig_b1_nombre_size'   => 40,
      'sig_b1_titulo_x'         => 20,  'sig_b1_titulo_y'   => 104, 'sig_b1_titulo_size'   => 11,
      'sig_b1_email_x'          => 63,  'sig_b1_email_y'    => 138, 'sig_b1_email_size'    => 11,
      'sig_b1_mobile_x'         => 63,  'sig_b1_mobile_y'   => 161, 'sig_b1_mobile_size'   => 11,
      'sig_b1_tel_x'            => 185, 'sig_b1_tel_y'      => 161, 'sig_b1_tel_size'      => 11,
      'sig_b1_ext_x'            => 283, 'sig_b1_ext_y'      => 161, 'sig_b1_ext_size'      => 11,
      'sig_b1_facebook_x'       => 63,  'sig_b1_facebook_y' => 183, 'sig_b1_facebook_size' => 11,
      'sig_b1_web_x'            => 185, 'sig_b1_web_y'      => 183, 'sig_b1_web_size'      => 11,
      'sig_b1_qr_x'             => 560, 'sig_b1_qr_y'       => 130,
      // ── Posiciones plantilla SIN celular (base2) ──────────────────────
      'sig_b2_nombre_x'         => 20,  'sig_b2_nombre_y'   => 75,  'sig_b2_nombre_size'   => 40,
      'sig_b2_titulo_x'         => 20,  'sig_b2_titulo_y'   => 104, 'sig_b2_titulo_size'   => 11,
      'sig_b2_email_x'          => 63,  'sig_b2_email_y'    => 138, 'sig_b2_email_size'    => 11,
      'sig_b2_tel_x'            => 63,  'sig_b2_tel_y'      => 161, 'sig_b2_tel_size'      => 11,
      'sig_b2_ext_x'            => 160, 'sig_b2_ext_y'      => 161, 'sig_b2_ext_size'      => 11,
      'sig_b2_facebook_x'       => 63,  'sig_b2_facebook_y' => 183, 'sig_b2_facebook_size' => 11,
      'sig_b2_web_x'            => 185, 'sig_b2_web_y'      => 183, 'sig_b2_web_size'      => 11,
   ];
}

function plugin_signatures_install(): bool {
   $dir = GLPI_PLUGIN_DOC_DIR . '/signatures/templates';
   if (!is_dir($dir)) {
      mkdir($dir, 0755, true);
   }

   // Solo aplicar defaults para claves que aún no existen
   $existing = Config::getConfigurationValues('plugin_signatures');
   $defaults  = plugin_signatures_getDefaults();
   $toSet     = array_diff_key($defaults, $existing);
   if (!empty($toSet)) {
      Config::setConfigurationValues('plugin_signatures', $toSet);
   }

   return true;
}

function plugin_signatures_uninstall(): bool {
   Config::deleteConfigurationValues(
      'plugin_signatures',
      array_keys(plugin_signatures_getDefaults())
   );
   return true;
}

/**
 * Migración al actualizar desde versiones anteriores.
 * Reutiliza install() que aplica defaults solo para claves inexistentes.
 * Garantiza que instalaciones existentes reciban las nuevas claves de config.
 *
 * @param string $fromVersion Versión de origen (provista por GLPI)
 */
function plugin_signatures_update(string $fromVersion): bool {
   return plugin_signatures_install();
}

function plugin_signatures_check_prerequisites(): bool {

   if (version_compare(PHP_VERSION, '8.1', '<')) {
      Session::addMessageAfterRedirect(
         __('Se requiere PHP 8.1 o superior para instalar este complemento.', 'signatures'),
         false,
         ERROR
      );
      return false;
   }

   if (!extension_loaded('gd')) {
      Session::addMessageAfterRedirect(
         __('La extensión GD de PHP es requerida para instalar este complemento.', 'signatures'),
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
