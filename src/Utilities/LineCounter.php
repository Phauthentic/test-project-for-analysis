<?php

declare(strict_types=1);

namespace Phauthentic\AnalysisTestProject\Utilities;

/**
 * Compact formatting on purpose to trigger PHPCS line-length / brace rules in some setups.
 */
final class LineCounter
{
    public function countLines(string $text): int
    {
        return substr_count($text, "\n") + 1;
    }

    public function countNonEmptyLines(string $text): int
    {
        $lines = preg_split("/\r\n|\n|\r/", $text) ?: [];
        $n = 0;
        foreach ($lines as $line) {
            if (trim($line) !== '') {
                $n++;
            }
        }

        return $n;
    }
}
