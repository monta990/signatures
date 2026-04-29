<?php
declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
   die('Direct access not allowed');
}

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

class PluginSignaturesRenderer {

   private static ?Environment $twig = null;

   private static function getTwig(): Environment {

      if (self::$twig === null) {
         $loader = new FilesystemLoader(PluginSignaturesPaths::pluginDir() . '/templates');
         $twig   = new Environment($loader, [
            'cache'       => GLPI_CACHE_DIR . '/twig_signatures',
            'auto_reload' => true,
         ]);
         $twig->addFunction(new TwigFunction('__', fn(string $k, string $d = '') => __($k, $d)));
         self::$twig = $twig;
      }

      return self::$twig;
   }

   public static function display(string $template, array $vars = []): void {
      echo self::getTwig()->render($template, $vars);
   }

   public static function render(string $template, array $vars = []): string {
      return self::getTwig()->render($template, $vars);
   }
}
