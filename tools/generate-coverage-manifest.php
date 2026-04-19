#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Writes coverage-manifest.full.json: one Cobertura import per commit (git rev-list --reverse HEAD).
 *
 * Usage: php generate-coverage-manifest.php [output-path]
 */

$out = $argv[1] ?? dirname(__DIR__) . '/coverage-manifest.full.json';

$commits = array_filter(explode("\n", trim((string)shell_exec('git rev-list --reverse HEAD'))));
$imports = [];
foreach ($commits as $sha) {
    $imports[] = [
        'commitSha' => $sha,
        'format' => 'cobertura',
        'file' => "reports/{$sha}-coverage-report.xml",
    ];
}

file_put_contents($out, json_encode(['imports' => $imports], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

echo 'Wrote ' . count($imports) . " rows to {$out}\n";
