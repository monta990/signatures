<?php

declare(strict_types=1);

/**
 * Validate the production layout and release metadata of the Signatures plugin.
 *
 * This is deliberately kept under .github so CI tooling does not alter the
 * installable plugin layout or introduce a runtime dependency.
 */

$root = dirname(__DIR__, 2);
$errors = [];

$read = static function (string $relativePath) use ($root, &$errors): string {
    $path = $root . '/' . $relativePath;
    if (!is_file($path) || !is_readable($path)) {
        $errors[] = "Required file is missing or unreadable: {$relativePath}";
        return '';
    }

    $contents = file_get_contents($path);
    if ($contents === false) {
        $errors[] = "Unable to read required file: {$relativePath}";
        return '';
    }

    return $contents;
};

$setup = $read('setup.php');
$pluginXmlContents = $read('plugin.xml');

$requiredFiles = [
    'README.md',
    'CHANGELOG.md',
    'LICENSE',
    'logo.png',
    'config.png',
    'user.png',
    'setup.php',
    'plugin.xml',
    'src/Config.php',
    'src/Controller/ConfigController.php',
    'src/Controller/DownloadController.php',
    'src/Controller/ResourceController.php',
    'src/Controller/SendController.php',
    'src/Paths.php',
    'src/Renderer.php',
    'src/Service/ConfigPage.php',
    'src/Service/UpdateChecker.php',
    'src/Signature.php',
    'src/UserTab.php',
    'templates/config_form.html.twig',
    'templates/user_tab.html.twig',
    'public/js/signatures-config.js',
    'public/js/signatures-user.js',
    'public/fonts/AvenirBlack.ttf',
    'public/fonts/AvenirRoman.ttf',
    'locales/signatures.pot',
    'locales/es_MX.po',
    'locales/es_MX.mo',
    'locales/fr_FR.po',
    'locales/fr_FR.mo',
];

foreach ($requiredFiles as $relativePath) {
    $read($relativePath);
}

$version = null;
if (preg_match(
    "/define\\('PLUGIN_SIGNATURES_VERSION',\\s*'([^']+)'\\)/",
    $setup,
    $versionMatch
) === 1) {
    $version = $versionMatch[1];
} else {
    $errors[] = 'PLUGIN_SIGNATURES_VERSION is missing from setup.php.';
}

if (preg_match("/define\\('PLUGIN_SIGNATURES_MIN_GLPI',\\s*'([^']+)'\\)/", $setup, $minGlpiMatch) !== 1
    || $minGlpiMatch[1] !== '11.0'
) {
    $errors[] = 'The declared minimum GLPI version must remain 11.0.';
}

if (preg_match("/define\\('PLUGIN_SIGNATURES_MAX_GLPI',\\s*'([^']+)'\\)/", $setup, $maxGlpiMatch) !== 1
    || $maxGlpiMatch[1] !== '12.99'
) {
    $errors[] = 'The declared maximum GLPI version must remain 12.99.';
}

if (!str_contains($setup, "'minphpversion' => '8.2'")) {
    $errors[] = 'The PHP minimum version declared by setup.php must remain 8.2.';
}
if (!str_contains($setup, "if (!extension_loaded('gd'))")) {
    $errors[] = 'The PHP GD prerequisite check is missing from setup.php.';
}

if (!class_exists(SimpleXMLElement::class)) {
    $errors[] = 'SimpleXML is required to validate plugin.xml.';
} else {
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($pluginXmlContents, SimpleXMLElement::class, LIBXML_NONET);
    if ($xml === false) {
        $errors[] = 'plugin.xml is not valid XML.';
    } else {
        if ((string) $xml->key !== 'signatures') {
            $errors[] = 'plugin.xml key must remain signatures.';
        }

        if ($version !== null) {
            $foundVersion = false;
            $compatibility = [];
            foreach ($xml->versions->version as $release) {
                if ((string) $release->num !== $version) {
                    continue;
                }

                $foundVersion = true;
                foreach ($release->compatibility as $supportedVersion) {
                    $compatibility[] = (string) $supportedVersion;
                }
            }

            sort($compatibility);
            $expectedCompatibility = ['~11.0', '~12.0'];
            sort($expectedCompatibility);

            if (!$foundVersion) {
                $errors[] = "plugin.xml has no release entry matching setup.php version {$version}.";
            } elseif ($compatibility !== $expectedCompatibility) {
                $errors[] = 'plugin.xml must retain the declared GLPI 11 and GLPI 12 compatibility entries.';
            }
        }
    }
}

$sourceDirectory = $root . '/src';
if (is_dir($sourceDirectory)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceDirectory, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
            continue;
        }

        $source = file_get_contents($file->getPathname());
        if ($source === false) {
            $errors[] = 'Unable to read PHP source: ' . $file->getPathname();
            continue;
        }

        if (preg_match('/^namespace\\s+([^;]+);/m', $source, $namespaceMatch) !== 1) {
            $errors[] = 'Missing namespace declaration: ' . $file->getPathname();
            continue;
        }

        if (preg_match(
            '/^(?:final\\s+|abstract\\s+)?(?:class|interface|trait|enum)\\s+([A-Za-z_][A-Za-z0-9_]*)/m',
            $source,
            $classMatch
        ) !== 1) {
            $errors[] = 'Missing named class-like declaration: ' . $file->getPathname();
            continue;
        }

        $relativePath = substr($file->getPathname(), strlen($sourceDirectory) + 1);
        $relativeClass = str_replace('/', '\\', substr($relativePath, 0, -4));
        $expectedClass = 'GlpiPlugin\\Signatures\\' . $relativeClass;
        $actualClass = trim($namespaceMatch[1], '\\') . '\\' . $classMatch[1];

        if ($actualClass !== $expectedClass) {
            $errors[] = "PSR-4 mismatch in {$relativePath}: expected {$expectedClass}, found {$actualClass}.";
        }
    }
}

foreach (['composer.json', 'composer.lock'] as $composerFile) {
    if (file_exists($root . '/' . $composerFile)) {
        $errors[] = "Unexpected Composer manifest in the production plugin root: {$composerFile}.";
    }
}

foreach (['locales/es_MX.po', 'locales/fr_FR.po', 'locales/signatures.pot'] as $catalog) {
    if (is_file($root . '/' . $catalog) && filesize($root . '/' . $catalog) === 0) {
        $errors[] = "Translation catalog is empty: {$catalog}.";
    }
}

if ($errors !== []) {
    fwrite(
        STDERR,
        implode(PHP_EOL, array_map(
            static fn (string $error): string => '[Signatures validation] ERROR: ' . $error,
            $errors
        )) . PHP_EOL
    );
    exit(1);
}

printf(
    "Signatures %s: production layout, GLPI compatibility metadata, runtime resources and PSR-4 contracts are valid.\n",
    $version ?? 'unknown'
);
