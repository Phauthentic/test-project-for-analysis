#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Writes a phploc-compatible JSON file (numeric summary only) by scanning src/ and tests/.
 * Avoids the phploc/phploc Composer dependency (PHP 8.4 / transitive conflicts with PHPUnit).
 *
 * Usage: php generate-phploc-style-metrics.php <project-root> <output-json-path>
 */

if ($argc < 3) {
    fwrite(STDERR, "Usage: php generate-phploc-style-metrics.php <project-root> <output-json-path>\n");
    exit(1);
}

$root = rtrim($argv[1], '/');
$outPath = $argv[2];

$scanDirs = [
    $root . '/src',
    $root . '/tests',
];

$phpFiles = [];
foreach ($scanDirs as $dir) {
    if (!is_dir($dir)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile()) {
            continue;
        }
        if (strtolower($fileInfo->getExtension()) !== 'php') {
            continue;
        }
        $phpFiles[] = $fileInfo->getPathname();
    }
}

$dirSet = [];
$loc = 0;
$blank = 0;
$cloc = 0;

foreach ($phpFiles as $path) {
    $dirSet[dirname($path)] = true;

    $raw = @file($path, FILE_IGNORE_NEW_LINES);
    if ($raw === false) {
        continue;
    }

    foreach ($raw as $line) {
        ++$loc;
        $t = trim($line);
        if ($t === '') {
            ++$blank;

            continue;
        }
        if (preg_match('/^(\/\/|#|\/\*|\*\/|\*)/', $t) === 1) {
            ++$cloc;

            continue;
        }
    }
}

$ncloc = max(0, $loc - $blank - $cloc);
$lloc = $ncloc;

$payload = [
    'directories' => count($dirSet),
    'files' => count($phpFiles),
    'loc' => (float)$loc,
    'lloc' => (float)$lloc,
    'cloc' => (float)$cloc,
    'ncloc' => (float)$ncloc,
    'ccn' => 0.0,
    'ccnMethods' => 0.0,
    'classCcnMin' => 0.0,
    'classCcnAvg' => 0.0,
    'classCcnMax' => 0.0,
    'methodCcnMin' => 0.0,
    'methodCcnAvg' => 0.0,
    'methodCcnMax' => 0.0,
    'averageMethodsPerClass' => 0.0,
];

$json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
if (file_put_contents($outPath, $json) === false) {
    fwrite(STDERR, "Failed to write: {$outPath}\n");
    exit(1);
}
