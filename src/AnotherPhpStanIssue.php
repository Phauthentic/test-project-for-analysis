<?php

declare(strict_types=1);

namespace Phauthentic\AnalysisTestProject;

/**
 * Second deliberate static analysis issue (argument type mismatch).
 */
final class AnotherPhpStanIssue
{
    public function expectsInt(int $x): int
    {
        return $x;
    }

    public function callWithWrongType(): void
    {
        $this->expectsInt('not-an-int');
    }
}
