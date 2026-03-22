<?php
declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
   die('Direct access not allowed');
}

/**
 * Centraliza el acceso a la configuración del plugin.
 *
 * Todas las llamadas a Config::getConfigurationValues('plugin_signatures')
 * pasan por aquí. El resultado se cachea en memoria para la duración de la
 * petición HTTP actual, evitando múltiples queries a glpi_configs.
 *
 * Para invalidar el caché manualmente (ej. tras guardar) usa invalidate().
 */
class PluginSignaturesConfig {

   /** @var array<string,mixed>|null Caché de una sola petición */
   private static ?array $cache = null;

   /**
    * Devuelve todos los valores de configuración del plugin.
    *
    * @return array<string,mixed>
    */
   public static function getAll(): array {
      if (self::$cache === null) {
         self::$cache = Config::getConfigurationValues('plugin_signatures');
      }
      return self::$cache;
   }

   /**
    * Devuelve el valor de una clave específica.
    *
    * @param string $key     Clave de configuración (ej. 'facebook_page')
    * @param mixed  $default Valor de retorno si la clave no existe
    */
   public static function get(string $key, mixed $default = ''): mixed {
      return self::getAll()[$key] ?? $default;
   }

   /**
    * Invalida el caché. Llamar tras guardar configuración.
    */
   public static function invalidate(): void {
      self::$cache = null;
   }

   /**
    * Devuelve los valores por defecto del plugin.
    * Delega en plugin_signatures_getDefaults() (setup.php) como única fuente de verdad.
    *
    * @return array<string,mixed>
    */
   public static function getDefaults(): array {
      return plugin_signatures_getDefaults();
   }
}
