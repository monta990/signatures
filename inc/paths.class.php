<?php
declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
   die('Direct access not allowed');
}

class PluginSignaturesPaths {

   /**
    * Directorio físico del plugin si está en plugins (legacy) o marketplace (GLPI 10+).
    */
   public static function pluginDir(): string {

      foreach (GLPI_PLUGINS_DIRECTORIES as $dir) {

         $path = $dir . '/signatures';

         if (is_dir($path)) {
            return $path;
         }
      }

      throw new RuntimeException('Plugin directory "signatures" not found');
   }


   /**
    * Directorio físico de plantillas PNG (files/)
    */
   public static function filesDir(): string {
      return GLPI_PLUGIN_DOC_DIR . '/signatures/templates';
   }

   /**
    * Directorio de fuentes subidas por el usuario.
    */
   public static function userFontsDir(): string {
      return GLPI_PLUGIN_DOC_DIR . '/signatures/fonts';
   }

   /**
    * Ruta física de la plantilla 1 (con celular)
    */
   public static function base1Path(): string {
      return self::filesDir() . '/base.png';
   }

   /**
    * Ruta física de la plantilla 2 (sin celular)
    */
   public static function base2Path(): string {
      return self::filesDir() . '/base2.png';
   }

   /** Filename of the built-in name font. */
   const BUILTIN_FONT_NAME = 'AvenirBlack.ttf';

   /** Filename of the built-in body font. */
   const BUILTIN_FONT_BODY = 'AvenirRoman.ttf';

   /**
    * Avenir Black — built-in name font (always available as fallback).
    */
   public static function builtinFontName(): string {
      return self::pluginDir() . '/public/fonts/' . self::BUILTIN_FONT_NAME;
   }

   /**
    * Avenir Roman — built-in body font (always available as fallback).
    */
   public static function builtinFontBody(): string {
      return self::pluginDir() . '/public/fonts/' . self::BUILTIN_FONT_BODY;
   }

   /**
    * Resuelve la ruta efectiva de la fuente bold.
    * Si el usuario configuró una fuente personalizada y existe en disco, la usa.
    * En cualquier otro caso retorna la fuente Avenir integrada.
    */
   public static function resolvedFontName(): string {
      return self::resolveUserFont('font_name', self::builtinFontName());
   }

   /**
    * Resuelve la ruta efectiva de la fuente regular.
    */
   public static function resolvedFontBody(): string {
      return self::resolveUserFont('font_body', self::builtinFontBody());
   }

   /**
    * Lógica interna de resolución: busca el nombre de archivo en la config,
    * lo verifica en el directorio de fuentes de usuario y retorna el fallback
    * si no existe o no es legible.
    */
   private static function resolveUserFont(string $configKey, string $fallback): string {
      $config   = PluginSignaturesConfig::getAll();
      $filename = trim((string)($config[$configKey] ?? ''));

      if ($filename !== '') {
         // Check user fonts directory first
         $userPath = self::userFontsDir() . '/' . $filename;
         if (is_readable($userPath)) {
            return $userPath;
         }

         // Check built-in fonts directory (allows explicitly selecting Avenir Black/Roman)
         $builtinPath = self::pluginDir() . '/fonts/' . $filename;
         if (is_readable($builtinPath)) {
            return $builtinPath;
         }
      }

      return $fallback;
   }

   /**
    * Lists user-uploaded font files.
    *
    * @return string[] Filenames (without path), sorted case-insensitively.
    */
   public static function listUserFonts(): array {
      $dir = self::userFontsDir();
      if (!is_dir($dir)) {
         return [];
      }
      $files = glob($dir . '/*.{ttf,otf,TTF,OTF}', GLOB_BRACE);
      if (!$files) {
         return [];
      }
      $names = array_map('basename', $files);
      natcasesort($names);
      return array_values($names);
   }

   /**
    * Lists user-uploaded font files with their internal display names.
    * Reads each font's `name` table — no caching, suitable for small collections.
    *
    * @return array<string, string>  ['filename.ttf' => 'Display Name']
    */
   public static function listUserFontsWithNames(): array {
      $result = [];
      foreach (self::listUserFonts() as $filename) {
         $path = self::userFontPath($filename);
         $result[$filename] = PluginSignaturesSignature::readFontDisplayName($path);
      }
      return $result;
   }

   /**
    * Ruta completa a una fuente de usuario por nombre de archivo.
    * No valida si existe — el caller debe hacerlo.
    */
   public static function userFontPath(string $filename): string {
      return self::userFontsDir() . '/' . $filename;
   }

   // ── URLs públicas ──────────────────────────────────────────────────────────

   public static function base1Url(): string {
      global $CFG_GLPI;
      return ($CFG_GLPI['root_doc'] ?? '') . '/plugins/signatures/front/resource.send.php?resource=base1';
   }

   public static function base2Url(): string {
      global $CFG_GLPI;
      return ($CFG_GLPI['root_doc'] ?? '') . '/plugins/signatures/front/resource.send.php?resource=base2';
   }

   /**
    * URL pública para servir una fuente de usuario al browser (para @font-face).
    */
   public static function userFontUrl(string $filename): string {
      global $CFG_GLPI;
      return ($CFG_GLPI['root_doc'] ?? '') . '/plugins/signatures/front/resource.send.php?resource=font&name='
         . rawurlencode($filename);
   }
}
