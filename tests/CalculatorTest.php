<?php

declare(strict_types=1);

namespace Phauthentic\AnalysisTestProject\Tests;

use PHPUnit\Framework\TestCase;

final class CalculatorTest extends TestCase
{
    public function testAddsSmallIntegers(): void
    {
        $this->assertSame(4, 2 + 2);
    }
}
