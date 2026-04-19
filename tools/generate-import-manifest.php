#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Writes manifest.full.json: phpstan, phpcs, phpunit, cognitive per commit (git rev-list --reverse HEAD).
 *
 * Usage: php generate-import-manifest.php [output-path]
 */

$out = $argv[1] ?? dirname(__DIR__) . '/manifest.full.json';

$commits = array_filter(explode("\n", trim((string)shell_exec('git rev-list --reverse HEAD'))));
$tools = [
    ['toolName' => 'phpstan', 'format' => 'github-actions', 'suffix' => 'phpstan-report.json'],
    ['toolName' => 'phpcs', 'format' => 'github-actions', 'suffix' => 'phpcs-report.json'],
    ['toolName' => 'phpunit', 'format' => 'github-actions', 'suffix' => 'phpunit-report.json'],
    ['toolName' => 'cognitive', 'format' => 'gitlab-code-quality', 'suffix' => 'cognitive-report.json'],
];

$imports = [];
foreach ($commits as $sha) {
    foreach ($tools as $t) {
        $imports[] = [
            'commitSha' => $sha,
            'toolName' => $t['toolName'],
            'format' => $t['format'],
            'file' => "reports/{$sha}-{$t['suffix']}",
        ];
    }
}

file_put_contents($out, json_encode(['imports' => $imports], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

echo 'Wrote ' . count($imports) . " rows to {$out}\n";
