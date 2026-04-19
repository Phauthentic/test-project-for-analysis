<?php

declare(strict_types=1);

namespace Phauthentic\AnalysisTestProject\Http;

/**
 * Minimal stub used as extra surface area for metrics and style checks.
 */
final class RequestStub
{
    public function __construct(
        private string $method,
        private string $path,
    ) {
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
