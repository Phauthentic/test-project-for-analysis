#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Converts PHPCS --report=json output to GitHub Checks annotations JSON for Domain Atlas.
 *
 * Usage: php convert-phpcs-json-to-github-annotations.php <phpcs.json> [projectRoot]
 */

if ($argc < 2) {
    fwrite(STDERR, "Usage: php convert-phpcs-json-to-github-annotations.php <phpcs.json> [projectRoot]\n");
    exit(1);
}

$inputPath = $argv[1];
$projectRoot = realpath($argv[2] ?? getcwd()) ?: getcwd();

if (!is_file($inputPath)) {
    echo "[]\n";
    exit(0);
}

$raw = file_get_contents($inputPath);
if ($raw === false || trim($raw) === '') {
    echo "[]\n";
    exit(0);
}

try {
    /** @var mixed $data */
    $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    fwrite(STDERR, 'Invalid JSON: ' . $e->getMessage() . "\n");
    exit(1);
}

$annotations = [];
$files = is_array($data) && isset($data['files']) && is_array($data['files']) ? $data['files'] : [];

foreach ($files as $absolutePath => $fileData) {
    if (!is_string($absolutePath) || !is_array($fileData)) {
        continue;
    }

    $relative = toProjectRelativePath($absolutePath, $projectRoot);
    if ($relative === null) {
        $relative = basename($absolutePath);
    }

    $messages = $fileData['messages'] ?? null;
    if (!is_array($messages)) {
        continue;
    }

    foreach ($messages as $msg) {
        if (!is_array($msg)) {
            continue;
        }

        $text = isset($msg['message']) ? (string)$msg['message'] : 'PHPCS issue';
        $line = isset($msg['line']) ? (int)$msg['line'] : 1;
        $source = isset($msg['source']) ? (string)$msg['source'] : 'phpcs';
        $type = isset($msg['type']) ? strtoupper((string)$msg['type']) : 'ERROR';

        $level = $type === 'WARNING' ? 'warning' : 'failure';

        $annotations[] = [
            'path' => str_replace('\\', '/', $relative),
            'start_line' => $line,
            'end_line' => $line,
            'annotation_level' => $level,
            'title' => $source,
            'message' => $text,
        ];
    }
}

echo json_encode($annotations, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
echo "\n";

/**
 * @return non-empty-string|null
 */
function toProjectRelativePath(string $absolutePath, string $projectRoot): ?string
{
    $absolutePath = str_replace('\\', '/', $absolutePath);
    $projectRoot = rtrim(str_replace('\\', '/', $projectRoot), '/');

    if (str_starts_with($absolutePath, $projectRoot . '/')) {
        $rel = substr($absolutePath, strlen($projectRoot) + 1);

        return $rel !== '' ? $rel : null;
    }

    return null;
}
