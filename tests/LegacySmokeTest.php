<?php

namespace Phauthentic\AnalysisTestProject\Tests;

use PHPUnit\Framework\TestCase;

class LegacySmokeTest extends TestCase
{
    public function testSmoke(): void
    {
        $this->assertSame(1, 1);
    }
}
