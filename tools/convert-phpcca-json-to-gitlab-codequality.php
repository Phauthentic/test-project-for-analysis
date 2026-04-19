#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Converts phauthentic cognitive-code-analysis JSON report to GitLab Code Quality JSON
 * accepted by Domain Atlas gitlab-code-quality importer.
 *
 * Emits findings for methods with score >= threshold (default: 5.0).
 *
 * Usage: php convert-phpcca-json-to-gitlab-codequality.php <cognitive.json> [projectRoot] [minScore]
 */

if ($argc < 2) {
    fwrite(
        STDERR,
        "Usage: php convert-phpcca-json-to-gitlab-codequality.php <cognitive.json> [projectRoot] [minScore]\n"
    );
    exit(1);
}

$inputPath = $argv[1];
$projectRoot = realpath($argv[2] ?? getcwd()) ?: getcwd();
$minScore = isset($argv[3]) ? (float)$argv[3] : 5.0;

$raw = file_get_contents($inputPath);
if ($raw === false) {
    fwrite(STDERR, "Cannot read: {$inputPath}\n");
    exit(1);
}

try {
    /** @var mixed $data */
    $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    fwrite(STDERR, 'Invalid JSON: ' . $e->getMessage() . "\n");
    exit(1);
}

if (!is_array($data)) {
    fwrite(STDERR, "Expected JSON object at root\n");
    exit(1);
}

$findings = [];
$baseNamespace = 'Phauthentic\\AnalysisTestProject\\';
$srcDir = $projectRoot . '/src';

foreach ($data as $className => $classBlock) {
    if (!is_string($className) || !is_array($classBlock)) {
        continue;
    }

    $methods = $classBlock['methods'] ?? null;
    if (!is_array($methods)) {
        continue;
    }

    foreach ($methods as $methodName => $metrics) {
        if (!is_string($methodName) || !is_array($metrics)) {
            continue;
        }

        $score = isset($metrics['score']) ? (float)$metrics['score'] : 0.0;
        if ($score < $minScore) {
            continue;
        }

        $fqcn = $className;
        $path = classNameToSrcPath($fqcn, $baseNamespace, $srcDir);
        if ($path === null || !is_file($path)) {
            continue;
        }

        $relative = toProjectRelativePath($path, $projectRoot);
        if ($relative === null) {
            continue;
        }

        $line = findMethodStartLine($path, $methodName);

        $fingerprint = sha1($fqcn . '::' . $methodName . ':' . $relative);

        $findings[] = [
            'description' => sprintf(
                'Cognitive score %.2f for %s::%s() (lineCount=%s, ifNesting=%s)',
                $score,
                shortClassName($fqcn),
                $methodName,
                isset($metrics['lineCount']) ? (string)$metrics['lineCount'] : '?',
                isset($metrics['ifNestingLevel']) ? (string)$metrics['ifNestingLevel'] : '?'
            ),
            'check_name' => 'cognitive.score',
            'fingerprint' => $fingerprint,
            'severity' => $score >= 25.0 ? 'major' : 'minor',
            'location' => [
                'path' => str_replace('\\', '/', $relative),
                'lines' => [
                    'begin' => $line,
                ],
            ],
        ];
    }
}

echo json_encode(array_values($findings), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
echo "\n";

function shortClassName(string $fqcn): string
{
    $pos = strrpos($fqcn, '\\');

    return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
}

/**
 * @return non-empty-string|null
 */
function classNameToSrcPath(string $fqcn, string $baseNamespace, string $srcDir): ?string
{
    if (!str_starts_with($fqcn, $baseNamespace)) {
        return null;
    }

    $rel = substr($fqcn, strlen($baseNamespace));
    $relPath = str_replace('\\', '/', $rel) . '.php';
    $full = $srcDir . '/' . $relPath;

    return $full;
}

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

function findMethodStartLine(string $file, string $methodName): int
{
    $content = file_get_contents($file);
    if ($content === false) {
        return 1;
    }

    $pattern = '/function\s+' . preg_quote($methodName, '/') . '\s*\(/';
    if (preg_match($pattern, $content, $m, PREG_OFFSET_CAPTURE)) {
        $before = substr($content, 0, $m[0][1]);

        return substr_count($before, "\n") + 1;
    }

    return 1;
}
