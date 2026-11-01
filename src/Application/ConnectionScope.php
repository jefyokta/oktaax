<?php

namespace Oktaax\Application;

use Closure;
use Oktaax\Event\Event;
use Oktaax\Exception\CleanupException;
use Swoole\Timer;

/** Owns resources that may outlive the HTTP handler, including SSE subscriptions. */
final class ConnectionScope
{
    private bool $closed = false;
    /**
     * Deferred cleanup callbacks attempted once in reverse registration order.
     *
     * @var list<\Closure(): mixed>
     */
    private array $cleanup = [];
    /**
     * Connection observers invoked in registration order within the owning application scope.
     *
     * @var list<\Closure(\Oktaax\Event\Event<array{aborted: bool, disconnected: bool}>): mixed>
     */
    private array $listeners = [];
    /**
     * Connection observers invoked in registration order within the owning application scope.
     *
     * @var list<\Closure(\Oktaax\Event\Event<array{aborted: bool, disconnected: bool}>): mixed>
     */
    private array $disconnectListeners = [];
    private ?\Oktaax\Application $application = null;

    public function bind(\Oktaax\Application $application): void
    {
        $this->application = $application;
    }

    /**
     * Register cleanup for this resource lifetime; callbacks must release only resources owned by this scope.
     *
     * @param \Closure(): mixed $callback
     */
    public function defer(Closure $callback): void
    {
        if ($this->closed) {
            $callback();
            return;
        }
        $this->cleanup[] = $callback;
    }

    /**
     * Observe connection closure, including normal completion and abort.
     *
     * @param callable(\Oktaax\Event\Event<array{aborted: bool, disconnected: bool}>): mixed $callback
     */
    public function onClose(callable $callback): void
    {
        if (!$this->closed) {
            $this->listeners[] = Closure::fromCallable($callback);
        }
    }

    /**
     * Observe detected native client disconnection only.
     *
     * @param callable(\Oktaax\Event\Event<array{aborted: bool, disconnected: bool}>): mixed $callback
     */
    public function onDisconnect(callable $callback): void
    {
        if (!$this->closed) {
            $this->disconnectListeners[] = Closure::fromCallable($callback);
        }
    }

    /**
     * Poll writability at a millisecond interval and cancel the watcher when the scope closes.
     *
     * @param callable(): bool $isWritable
     */
    public function watch(callable $isWritable, int $interval = 10): void
    {
        if ($this->closed) {
            return;
        }
        $timer = Timer::tick($interval, function () use ($isWritable) {
            if (!$isWritable()) {
                $this->close(true, true);
            }
        });
        $this->defer(static function () use ($timer) {
            if ($timer !== false && Timer::exists($timer)) {
                Timer::clear($timer);
            }
        });
    }

    /**
     * Close once and attempt every owned cleanup callback; additional failures are aggregated.
     *
     * @throws \Throwable When one or more cleanup callbacks fail.
     */
    public function close(
        bool $aborted = false,
        bool $disconnected = false,
    ): void {
        if ($this->closed) {
            return;
        }
        $this->closed = true;
        $event = new Event('close', [
            'aborted' => $aborted,
            'disconnected' => $disconnected,
        ]);
        $callbacks = array_reverse($this->cleanup);
        foreach ($this->listeners as $listener) {
            $callbacks[] = static fn() => $listener($event);
        }
        if ($disconnected) {
            foreach ($this->disconnectListeners as $listener) {
                $callbacks[] = static fn() => $listener($event);
            }
        }
        $this->cleanup = $this->listeners = $this->disconnectListeners = [];
        $application = $this->application;
        $this->application = null;
        if ($application !== null) {
            $application->within(fn() => CleanupException::run($callbacks));
        } else {
            CleanupException::run($callbacks);
        }
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }
}
