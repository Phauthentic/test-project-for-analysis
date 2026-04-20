#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Writes metrics-manifest.full.json: one phploc JSON import per commit (git rev-list --reverse HEAD).
 *
 * Usage: php generate-metrics-manifest.php [output-path]
 */

$out = $argv[1] ?? dirname(__DIR__) . '/metrics-manifest.full.json';

$commits = array_filter(explode("\n", trim((string)shell_exec('git rev-list --reverse HEAD'))));
$imports = [];
foreach ($commits as $sha) {
    $imports[] = [
        'commitSha' => $sha,
        'toolName' => 'phploc',
        'format' => 'phploc-json',
        'file' => "reports/{$sha}-phploc-report.json",
    ];
}

file_put_contents($out, json_encode(['imports' => $imports], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

echo 'Wrote ' . count($imports) . " rows to {$out}\n";
