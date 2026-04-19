#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Converts PHPUnit JUnit XML log to GitHub Checks annotations JSON.
 *
 * Usage: php convert-junit-to-github-annotations.php <junit.xml> [projectRoot]
 */

if ($argc < 2) {
    fwrite(STDERR, "Usage: php convert-junit-to-github-annotations.php <junit.xml> [projectRoot]\n");
    exit(1);
}

$inputPath = $argv[1];
$projectRoot = realpath($argv[2] ?? getcwd()) ?: getcwd();

$xml = file_get_contents($inputPath);
if ($xml === false) {
    fwrite(STDERR, "Cannot read: {$inputPath}\n");
    exit(1);
}

$use = @simplexml_load_string($xml);
if ($use === false) {
    fwrite(STDERR, "Invalid XML\n");
    exit(1);
}

$annotations = [];

foreach ($use->xpath('//testcase') ?: [] as $case) {
    /** @var SimpleXMLElement $case */
    $file = (string)($case['file'] ?? '');
    $line = isset($case['line']) ? (int)$case['line'] : 1;
    $name = (string)($case['name'] ?? 'test');

    $relative = $file !== '' ? toProjectRelativePath($file, $projectRoot) : 'unknown.php';
    if ($relative === null) {
        $relative = basename($file);
    }

    foreach (['failure', 'error'] as $tag) {
        $nodes = $case->{$tag};
        if ($nodes === null) {
            continue;
        }

        foreach ($nodes as $node) {
            $body = trim((string)$node);
            $annotations[] = [
                'path' => str_replace('\\', '/', $relative),
                'start_line' => $line,
                'end_line' => $line,
                'annotation_level' => 'failure',
                'title' => $name,
                'message' => $body !== '' ? $body : ($tag === 'failure' ? 'Test failed' : 'Test error'),
            ];
        }
    }
}

echo json_encode(array_values($annotations), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
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
