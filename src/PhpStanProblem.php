<?php

declare(strict_types=1);

namespace Phauthentic\AnalysisTestProject;

/**
 * Intentional PHPStan violation: return type does not match returned value.
 */
final class PhpStanProblem
{
    public function brokenReturn(): string
    {
        return 42;
    }
}
