<?php
declare(strict_types=1);

namespace GlpiPlugin\Signatures;

use Glpi\Application\View\TemplateRenderer;

final class Renderer
{
   public static function display(string $template, array $vars = []): void
   {
      TemplateRenderer::getInstance()->display('@signatures/' . ltrim($template, '/'), $vars);
   }

   public static function render(string $template, array $vars = []): string
   {
      return TemplateRenderer::getInstance()->render('@signatures/' . ltrim($template, '/'), $vars);
   }
}
