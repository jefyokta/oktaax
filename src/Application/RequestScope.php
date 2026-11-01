<?php

namespace Oktaax\Application;

use Closure;
use LogicException;
use Oktaax\Exception\CleanupException;

final class RequestScope
{
    /**
     * Deferred cleanup callbacks attempted once in reverse registration order.
     *
     * @var list<\Closure(): mixed>
     */
    private array $callbacks = [];
    private bool $closed = false;

    /**
     * Register cleanup for this resource lifetime; callbacks must release only resources owned by this scope.
     *
     * @param \Closure(): mixed $callback
     */
    public function defer(Closure $callback): void
    {
        if ($this->closed) {
            throw new LogicException('Request scope is already closed.');
        }
        $this->callbacks[] = $callback;
    }

    /**
     * Close once and attempt every owned cleanup callback; additional failures are aggregated.
     *
     * @throws \Throwable When one or more cleanup callbacks fail.
     */
    public function close(): void
    {
        if ($this->closed) {
            return;
        }
        $this->closed = true;
        $callbacks = array_reverse($this->callbacks);
        $this->callbacks = [];
        CleanupException::run($callbacks);
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }
}
