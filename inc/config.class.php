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

   /**
    * Returns the latest stable GitHub release version.
    * Result is cached for six hours and a stale valid value is used if GitHub
    * is temporarily unavailable.
    */
   public static function getLatestReleaseVersion(): ?string {
      $cacheDir = GLPI_CACHE_DIR . '/signatures';
      $cache    = $cacheDir . '/release_cache.json';
      $ttl      = 21600;
      $stale    = null;

      if (is_file($cache) && is_readable($cache)) {
         $cached = json_decode((string)@file_get_contents($cache), true);
         if (is_array($cached) && isset($cached['version'])) {
            $candidate = (string)$cached['version'];
            if (preg_match('/^\d+\.\d+\.\d+$/', $candidate)) {
               $stale = $candidate;
               if (!empty($cached['checked_at']) && (time() - (int)$cached['checked_at']) < $ttl) {
                  return $candidate;
               }
            }
         }
      }

      $context = stream_context_create([
         'http' => [
            'method'        => 'GET',
            'header'        => "User-Agent: GLPI-Signatures/1.7.6\r\nAccept: application/vnd.github+json\r\nX-GitHub-Api-Version: 2022-11-28\r\n",
            'timeout'       => 4,
            'ignore_errors' => true,
         ],
      ]);

      $json = @file_get_contents(
         'https://api.github.com/repos/monta990/signatures/releases/latest',
         false,
         $context,
         0,
         65536
      );

      $status = 0;
      foreach (($http_response_header ?? []) as $header) {
         if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $header, $m)) {
            $status = (int)$m[1];
         }
      }
      if ($json === false || $status !== 200) {
         return $stale;
      }

      $data = json_decode($json, true);
      if (!is_array($data) || empty($data['tag_name']) || !empty($data['draft']) || !empty($data['prerelease'])) {
         return $stale;
      }

      $version = preg_replace('/^[vV]/', '', trim((string)$data['tag_name']));
      if (!is_string($version) || !preg_match('/^\d+\.\d+\.\d+$/', $version)) {
         return $stale;
      }

      if (!is_dir($cacheDir) && !@mkdir($cacheDir, 0750, true) && !is_dir($cacheDir)) {
         return $version;
      }

      $payload = json_encode(['checked_at' => time(), 'version' => $version], JSON_UNESCAPED_SLASHES);
      if (is_string($payload)) {
         try {
            $suffix = bin2hex(random_bytes(6));
         } catch (Throwable) {
            $suffix = uniqid('', true);
         }
         $tmp = $cache . '.' . $suffix . '.tmp';
         if (@file_put_contents($tmp, $payload, LOCK_EX) !== false) {
            @chmod($tmp, 0640);
            if (!@rename($tmp, $cache)) {
               @unlink($tmp);
            }
         }
      }

      return $version;
   }

}
