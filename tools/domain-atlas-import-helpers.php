<?php

declare(strict_types=1);

/**
 * Domain Atlas import helpers (shared by run-import-manifest and run-coverage-import-manifest).
 */

/**
 * Strips any path from DOMAIN_ATLAS_BASE_URL so only scheme + host (+ port) remain.
 * If BASE_URL is set to e.g. http://host/api/code-analysis, appending /api/code-coverage/... would break routing.
 */
function normalizeDomainAtlasBaseUrl(string $raw): string
{
    $raw = rtrim(trim($raw), '/');
    if ($raw === '') {
        return '';
    }

    $parts = parse_url($raw);
    if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
        return $raw;
    }

    $port = isset($parts['port']) ? ':' . $parts['port'] : '';

    return $parts['scheme'] . '://' . $parts['host'] . $port;
}

/**
 * True when this row should be imported via code-coverage API, not code-analysis.
 */
function isCoverageOnlyImportRow(string $file, string $format, string $toolName): bool
{
    $fileLower = strtolower($file);
    $formatLower = strtolower($format);
    $toolLower = strtolower($toolName);

    if ($formatLower === 'cobertura') {
        return true;
    }

    if (str_contains($fileLower, '-coverage-report.xml')) {
        return true;
    }

    if (in_array($toolLower, ['cobertura', 'coverage'], true)) {
        return true;
    }

    return false;
}

/**
 * PHPUnit JUnit → annotations JSON must not use the code-analysis import API; use Cobertura on code-coverage.
 */
function isPhpunitToolForbiddenForAnalysisImport(string $toolName): bool
{
    return strtolower($toolName) === 'phpunit';
}
