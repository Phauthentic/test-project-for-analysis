<?php

declare(strict_types=1);

namespace Phauthentic\AnalysisTestProject\Tests;

use PHPUnit\Framework\TestCase;

final class LegacySmokeTest extends TestCase
{
    public function testSmoke(): void
    {
        $this->assertSame(1, 1);
    }
}
