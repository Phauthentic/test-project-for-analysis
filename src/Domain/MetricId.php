<?php

declare(strict_types=1);

namespace Phauthentic\AnalysisTestProject\Domain;

/** Value object with an intentionally long line for line-length sniffs when enabled. */
final readonly class MetricId
{
    public function __construct(public string $value)
    {
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
