<?php

declare(strict_types=1);

/**
 * Loads project-root `.env.local` into the environment when variables are not already set.
 * Does not depend on vlucas/phpdotenv (KEY=value lines, optional quotes, # comments).
 */
function loadEnvLocalFile(string $projectRoot): void
{
    $path = $projectRoot . DIRECTORY_SEPARATOR . '.env.local';
    if (!is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        if (str_starts_with($trimmed, 'export ')) {
            $trimmed = trim(substr($trimmed, 7));
        }

        $eq = strpos($trimmed, '=');
        if ($eq === false) {
            continue;
        }

        $name = trim(substr($trimmed, 0, $eq));
        $value = trim(substr($trimmed, $eq + 1));

        if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
            $quote = $value[0];
            $value = trim($value, $quote);
        }

        if ($name === '') {
            continue;
        }

        if (getenv($name) !== false) {
            continue;
        }

        putenv("{$name}={$value}");
        $_ENV[$name] = $value;
    }
}

$__projectRoot = dirname(__DIR__);
loadEnvLocalFile($__projectRoot);
unset($__projectRoot);
