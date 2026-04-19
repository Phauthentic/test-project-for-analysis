<?php

declare(strict_types=1);

namespace Phauthentic\AnalysisTestProject\Legacy;

/**
 * Intentional PHPCS / style issues for fixture data.
 */
class Scratchpad
{
    // Missing visibility on purpose (PSR12 / Squiz)
    function add(int $a, int $b): int
    {
        return $a + $b;
    }
}
