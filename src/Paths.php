<?php
declare(strict_types=1);

namespace GlpiPlugin\Signatures;

final class Paths
{
   public const BUILTIN_FONT_NAME = 'AvenirBlack.ttf';
   public const BUILTIN_FONT_BODY = 'AvenirRoman.ttf';

   public static function pluginDir(): string
   {
      if (defined('GLPI_PLUGINS_DIRECTORIES')) {
         foreach (GLPI_PLUGINS_DIRECTORIES as $dir) {
            $path = rtrim($dir, '/\\') . '/signatures';
            if (is_dir($path)) {
               return $path;
            }
         }
      }

      return dirname(__DIR__);
   }

   /**
    * Public plugin URL. GLPI 11+ deliberately uses /plugins even when the
    * plugin is physically installed through /marketplace.
    */
   public static function webDir(): string
   {
      global $CFG_GLPI;
      return rtrim((string)($CFG_GLPI['root_doc'] ?? ''), '/') . '/plugins/signatures';
   }

   public static function routeUrl(string $route = '', array $query = []): string
   {
      $url = self::webDir() . ($route !== '' ? '/' . ltrim($route, '/') : '');
      if ($query !== []) {
         $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
      }
      return $url;
   }

   public static function configUrl(): string { return self::routeUrl('config'); }
   public static function downloadUrl(): string { return self::routeUrl('download'); }
   public static function sendUrl(): string { return self::routeUrl('send'); }
   public static function resourceUrl(string $resource, array $query = []): string
   {
      return self::routeUrl('resource', array_merge(['resource' => $resource], $query));
   }

   public static function filesDir(): string
   {
      return rtrim((string)GLPI_PLUGIN_DOC_DIR, '/\\') . '/signatures/templates';
   }

   public static function userFontsDir(): string
   {
      return rtrim((string)GLPI_PLUGIN_DOC_DIR, '/\\') . '/signatures/fonts';
   }

   public static function cacheDir(): string
   {
      return rtrim((string)GLPI_PLUGIN_DOC_DIR, '/\\') . '/signatures';
   }

   public static function base1Path(): string { return self::filesDir() . '/base.png'; }
   public static function base2Path(): string { return self::filesDir() . '/base2.png'; }

   public static function builtinFontName(): string
   {
      return self::pluginDir() . '/public/fonts/' . self::BUILTIN_FONT_NAME;
   }

   public static function builtinFontBody(): string
   {
      return self::pluginDir() . '/public/fonts/' . self::BUILTIN_FONT_BODY;
   }

   public static function resolvedFontName(): string
   {
      return self::resolveUserFont('font_name', self::builtinFontName());
   }

   public static function resolvedFontBody(): string
   {
      return self::resolveUserFont('font_body', self::builtinFontBody());
   }

   private static function resolveUserFont(string $configKey, string $fallback): string
   {
      $config = Config::getAll();
      $filename = basename(trim((string)($config[$configKey] ?? '')));
      if ($filename !== '') {
         $userPath = self::userFontPath($filename);
         if (is_readable($userPath)) {
            return $userPath;
         }
         $builtinPath = self::pluginDir() . '/public/fonts/' . $filename;
         if (is_readable($builtinPath)) {
            return $builtinPath;
         }
      }
      return $fallback;
   }

   public static function listUserFonts(): array
   {
      $dir = self::userFontsDir();
      if (!is_dir($dir)) return [];
      $files = glob($dir . '/*.{ttf,otf,TTF,OTF}', GLOB_BRACE);
      if (!$files) return [];
      $names = array_map('basename', $files);
      natcasesort($names);
      return array_values($names);
   }

   public static function listUserFontsWithNames(): array
   {
      $result = [];
      foreach (self::listUserFonts() as $filename) {
         $result[$filename] = Signature::readFontDisplayName(self::userFontPath($filename));
      }
      return $result;
   }

   public static function userFontPath(string $filename): string
   {
      return self::userFontsDir() . '/' . basename($filename);
   }

   public static function base1Url(): string { return self::resourceUrl('base1'); }
   public static function base2Url(): string { return self::resourceUrl('base2'); }
   public static function userFontUrl(string $filename): string
   {
      return self::resourceUrl('font', ['name' => basename($filename)]);
   }
}
