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
      'version'      => '1.0.0',
      'author'       => 'Edwin Elias Alvarez',
      'license'      => 'GPLv2+',
      'homepage'     => 'https://sontechs.com',
      'requirements' => [
         'glpi' => ['min' => '11.0']
      ]
   ];
}

function plugin_signatures_install(): bool {
   $dir = GLPI_PLUGIN_DOC_DIR . '/signatures/templates';
   if (!is_dir($dir)) {
      mkdir($dir, 0755, true);
   }
   return true;
}

function plugin_signatures_uninstall(): bool {
   Config::deleteConfigurationValues(
      'plugin_signatures',
      ['facebook_page', 'whatsapp_country_code']
   );
   return true;
}

function plugin_signatures_check_prerequisites(): bool {
   return true;
}

function plugin_signatures_check_config(): bool {
   return true;
}