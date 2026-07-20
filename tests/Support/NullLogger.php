<?php

namespace RiseTechApps\FormRequest\Tests\Support;

/**
 * Fluent sink that discards everything, used by the logglyError() stub.
 */
class NullLogger
{
    /**
     * @param array<int, mixed> $arguments
     */
    public function __call(string $method, array $arguments): self
    {
        return $this;
    }
}
