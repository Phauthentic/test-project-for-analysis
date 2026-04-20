#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/load-env-local.php';
require_once __DIR__ . '/domain-atlas-import-helpers.php';

/**
 * POSTs each manifest entry to Domain Atlas code-analysis import API (phpstan, phpcs, cognitive).
 * Rejects toolName phpunit and Cobertura/coverage rows.
 *
 * Env: DOMAIN_ATLAS_BASE_URL, DOMAIN_ATLAS_TOKEN, SOURCE_REPOSITORY_ID
 * Env: DRY_RUN=1 to print only
 */

if ($argc < 2) {
    fwrite(STDERR, "Usage: php run-import-manifest.php <manifest.json>\n");
    exit(1);
}

$manifestPath = $argv[1];
$raw = file_get_contents($manifestPath);
if ($raw === false) {
    fwrite(STDERR, "Cannot read: {$manifestPath}\n");
    exit(1);
}

/** @var mixed $manifest */
$manifest = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
$imports = $manifest['imports'] ?? null;
if (!is_array($imports)) {
    fwrite(STDERR, "manifest.json must contain an \"imports\" array\n");
    exit(1);
}

$baseUrl = normalizeDomainAtlasBaseUrl((string)(getenv('DOMAIN_ATLAS_BASE_URL') ?: ''));
$token = (string)(getenv('DOMAIN_ATLAS_TOKEN') ?: '');
$repoId = (string)(getenv('SOURCE_REPOSITORY_ID') ?: '');
$dry = (string)(getenv('DRY_RUN') ?: '') === '1';

if ($baseUrl === '' || $token === '' || $repoId === '') {
    fwrite(STDERR, "Set DOMAIN_ATLAS_BASE_URL, DOMAIN_ATLAS_TOKEN, SOURCE_REPOSITORY_ID\n");
    exit(1);
}

$manifestDir = realpath(dirname($manifestPath)) ?: dirname($manifestPath);
$fail = 0;

foreach ($imports as $row) {
    if (!is_array($row)) {
        continue;
    }

    $commitSha = isset($row['commitSha']) ? (string)$row['commitSha'] : '';
    $toolName = isset($row['toolName']) ? (string)$row['toolName'] : '';
    $format = isset($row['format']) ? (string)$row['format'] : '';
    $file = isset($row['file']) ? (string)$row['file'] : '';

    if ($commitSha === '' || $toolName === '' || $format === '' || $file === '') {
        fwrite(STDERR, "Skipping incomplete row\n");
        $fail = 1;
        continue;
    }

    if (isPhpunitToolForbiddenForAnalysisImport($toolName)) {
        fwrite(STDERR, "Refusing code-analysis import for toolName \"phpunit\" (JUnit test-run report).\n");
        fwrite(STDERR, "Use Cobertura on the code-coverage API: php tools/run-coverage-import-manifest.php coverage-manifest.full.json\n");
        $fail = 1;
        continue;
    }

    if (isCoverageOnlyImportRow($file, $format, $toolName)) {
        fwrite(STDERR, "Refusing code-analysis import for Cobertura/coverage row (file={$file}).\n");
        fwrite(STDERR, "Use: php tools/run-coverage-import-manifest.php coverage-manifest.full.json\n");
        $fail = 1;
        continue;
    }

    $abs = $file;
    if (!str_starts_with($abs, '/')) {
        $candidate = $manifestDir . '/' . $file;
        $abs = is_file($candidate) ? $candidate : $file;
    }

    if (!is_file($abs)) {
        fwrite(STDERR, "Missing file: {$file}\n");
        $fail = 1;
        continue;
    }

    $url = $baseUrl . '/api/code-analysis/repositories/' . rawurlencode($repoId)
        . '/commits/' . rawurlencode($commitSha) . '/imports';

    $short = substr($commitSha, 0, 7);
    echo "POST {$toolName} ({$format}) commit={$short}…\n";

    if ($dry) {
        echo "  DRY_RUN: {$url}\n";
        echo "  file: {$abs}\n";
        continue;
    }

    $ch = curl_init($url);
    if ($ch === false) {
        fwrite(STDERR, "curl_init failed\n");
        $fail = 1;
        continue;
    }

    $cfile = new CURLFile($abs, 'application/json', basename($abs));

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => [
            'toolName' => $toolName,
            'format' => $format,
            'file' => $cfile,
        ],
    ]);

    $response = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($code !== 202 && $code !== 200) {
        fwrite(STDERR, "  HTTP {$code}\n");
        if (is_string($response)) {
            fwrite(STDERR, $response . "\n");
        }
        $fail = 1;
    } else {
        echo "  ok ({$code})\n";
    }
}

exit($fail);
