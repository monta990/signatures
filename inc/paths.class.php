<?php
declare(strict_types=1);

class PluginSignaturesPaths {
    
    /**
    * Directorio físico del plugin si esta en plugins (legacy) o marketplace glpi 10+)
    */
    public static function pluginDir(): string {
    
       foreach (GLPI_PLUGINS_DIRECTORIES as $dir) {
    
          $path = $dir . '/signatures';
    
          if (is_dir($path)) {
             return $path;
          }
       }
    
       throw new RuntimeException('No se encontró el directorio del complemento Signatures');
    }

   /**
    * Directorio físico (files/)
    */
   public static function filesDir(): string {
      return GLPI_PLUGIN_DOC_DIR . '/signatures/templates';
   }

   /**
    * Ruta física de la base uno
    */
   public static function base1Path(): string {
      return self::filesDir() . '/base.png';
   }

   /**
    * Ruta física de la base dos
    */
   public static function base2Path(): string {
      return self::filesDir() . '/base2.png';
   }

   /**
    * Ruta física de las fuentes
    */
    public static function getFontAvenirBlack(): string {
       return self::pluginDir() . '/fonts/AvenirBlack.ttf';
    }
    
    public static function getFontAvenirRoman(): string {
       return self::pluginDir() . '/fonts/AvenirRoman.ttf';
    }

    public static function base1Url(): string {
       return Plugin::getWebDir('signatures') . '/front/resource.send.php?resource=base1';
    }
    
    public static function base2Url(): string {
       return Plugin::getWebDir('signatures') . '/front/resource.send.php?resource=base2';
    }

}