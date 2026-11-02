<?php

declare(strict_types=1);

/**
 * Validate the plugin's src/ PSR-4 namespace and class-to-file mapping.
 *
 * Usage: php .github/scripts/check-psr4.php [src-directory] [namespace-prefix]
 */

$sourceRoot = $argv[1] ?? dirname(__DIR__, 2) . '/src';
$namespacePrefix = trim($argv[2] ?? 'GlpiPlugin\\Signatures', '\\');

if (!is_dir($sourceRoot) || $namespacePrefix === '') {
    fwrite(STDERR, "Usage: php check-psr4.php <src-directory> <namespace-prefix>\n");
    exit(2);
}

$sourceRoot = realpath($sourceRoot);
if ($sourceRoot === false) {
    fwrite(STDERR, "Unable to resolve the source directory.\n");
    exit(2);
}

$errors = [];
$fileCount = 0;
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }

    $fileCount++;
    $path = $file->getPathname();
    $source = file_get_contents($path);

    if ($source === false) {
        $errors[] = "Unable to read {$path}.";
        continue;
    }

    if (preg_match('/^namespace\\s+([^;]+);/m', $source, $namespaceMatch) !== 1) {
        $errors[] = "Missing namespace declaration: {$path}.";
        continue;
    }

    if (preg_match(
        '/^(?:final\\s+|abstract\\s+)?(?:class|interface|trait|enum)\\s+([A-Za-z_][A-Za-z0-9_]*)/m',
        $source,
        $classMatch
    ) !== 1) {
        $errors[] = "No named class-like declaration found: {$path}.";
        continue;
    }

    $relativePath = substr($path, strlen($sourceRoot) + 1);
    $relativeClass = str_replace('/', '\\', substr($relativePath, 0, -4));
    $expectedNamespace = $namespacePrefix;
    $relativeDirectory = dirname($relativePath);

    if ($relativeDirectory !== '.') {
        $expectedNamespace .= '\\' . str_replace('/', '\\', $relativeDirectory);
    }

    $actualNamespace = trim($namespaceMatch[1], '\\');
    $expectedClass = $expectedNamespace . '\\' . pathinfo($relativePath, PATHINFO_FILENAME);
    $actualClass = $actualNamespace . '\\' . $classMatch[1];

    if ($actualNamespace !== $expectedNamespace || $actualClass !== $expectedClass) {
        $errors[] = "PSR-4 mismatch in {$relativePath}: expected {$expectedClass}, found {$actualClass}.";
    }
}

if ($fileCount === 0) {
    $errors[] = "No PHP source files found in {$sourceRoot}.";
}

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

printf(
    "PSR-4 OK: %d PHP source files follow namespace %s and their directory/file mapping.\n",
    $fileCount,
    $namespacePrefix
);
