<?php

declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Repository root not found.\n");
    exit(1);
}

$excluded = [
    DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . '.phpunit.cache' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . '.phpstan-cache' . DIRECTORY_SEPARATOR,
];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

$failed = [];

foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
        continue;
    }

    $path = $file->getPathname();
    $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    foreach ($excluded as $needle) {
        if (str_contains($normalized, $needle)) {
            continue 2;
        }
    }

    $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path);
    exec($command, $output, $code);
    if ($code !== 0) {
        $failed[] = $path;
        fwrite(STDERR, implode("\n", $output) . "\n");
    }
    $output = [];
}

if ($failed !== []) {
    fwrite(STDERR, "PHP syntax lint failed for " . count($failed) . " file(s).\n");
    exit(1);
}

echo "PHP syntax lint passed.\n";

