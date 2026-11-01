<?php

namespace Oktaax\Application;

use Closure;
use Oktaax\Application;
use Oktaax\Exception\CleanupException;
use Oktaax\Http\Request;
use Oktaax\Http\Response;
use SplObjectStorage;

final class WorkerLifecycle
{
    private bool $started = false;
    private bool $draining = false;
    /**
     * Deferred cleanup callbacks attempted once in reverse registration order.
     *
     * @var list<\Closure(): mixed>
     */
    private array $cleanup = [];
    /**
     * Worker-owned native timer IDs awaiting cancellation.
     *
     * @var array<int, true>
     */
    private array $timers = [];

    public function trackTimer(int $timer): void
    {
        if ($this->draining) {
            if (\Swoole\Timer::exists($timer)) {
                \Swoole\Timer::clear($timer);
            }
            throw new \LogicException(
                'Cannot schedule timers while the application is stopping.',
            );
        }
        $this->timers[$timer] = true;
    }
    public function forgetTimer(int $timer): void
    {
        unset($this->timers[$timer]);
    }
    /**
     * HTTP connection scopes paired with descriptor metadata and weak response references.
     *
     * @var \SplObjectStorage<\Oktaax\Application\ConnectionScope, array{fd: int|null, response: \WeakReference<\Oktaax\Http\Response>}>
     */
    private SplObjectStorage $connections;

    public function __construct(private Application $application)
    {
        $this->connections = new SplObjectStorage();
    }

    public function start(
        int $id,
        bool $taskWorker = false,
        ?callable $callback = null,
    ): void {
        if ($this->started) {
            throw new \LogicException('Worker is already started.');
        }
        $app = $this->application;
        $app->container()->startWorker();
        $this->started = true;
        $this->draining = false;
        $app->worker = new WorkerContext($id, $taskWorker, $app);
        $app->container()->instance(WorkerContext::class, $app->worker);
        try {
            $app->within(function () use ($app, $callback) {
                foreach ($app->workerStartCallbacks() as $hook) {
                    $hook($app->worker, $app);
                }
                if ($callback !== null) {
                    CallbackInvoker::call(
                        $callback,
                        [
                            $app,
                            $app->nativeServer(),
                            $app->worker,
                            $app->address(),
                        ],
                        $app->address()->getHttpUrl(),
                    );
                }
            });
        } catch (\Throwable $error) {
            try {
                $this->stop();
            } catch (\Throwable $cleanup) {
                error_log((string) $cleanup);
            }
            throw $error;
        }
    }

    /**
     * Register cleanup for this resource lifetime; callbacks must release only resources owned by this scope.
     *
     * @param \Closure(): mixed $callback
     */
    public function defer(Closure $callback): void
    {
        if (!$this->started) {
            throw new \LogicException('Worker scope has not started.');
        }
        $this->cleanup[] = $callback;
    }

    public function track(Request $request, Response $response): void
    {
        $scope = $request->connection();
        if ($scope->isClosed() || $this->connections->offsetExists($scope)) {
            return;
        }
        $this->connections[$scope] = [
            'fd' => $request->fd,
            'response' => \WeakReference::create($response),
        ];
        $scope->defer(function () use ($scope) {
            unset($this->connections[$scope]);
        });
    }

    public function disconnect(int $fd): void
    {
        $callbacks = [
            fn() => $this->application->websockets()->disconnect($fd),
        ];
        foreach (clone $this->connections as $scope) {
            if ($this->connections[$scope]['fd'] === $fd) {
                $callbacks[] = fn() => $scope->close(true, true);
            }
        }
        CleanupException::run($callbacks);
    }

    /** Cancel background work before the native worker waits for its event loop to drain. */
    public function drain(): void
    {
        if ($this->draining) {
            return;
        }
        $app = $this->application;
        $this->draining = true;
        $callbacks = [
            fn() => $app->tasks()->pending()->close(),
            fn() => $app->websockets()->drain(),
        ];
        foreach (array_keys($this->timers) as $timer) {
            $callbacks[] = static function () use ($timer) {
                if (\Swoole\Timer::exists($timer)) {
                    \Swoole\Timer::clear($timer);
                }
            };
        }
        $this->timers = [];
        $callbacks[] = fn() => $this->closeConnections(true);
        $app->within(fn() => CleanupException::run($callbacks));
    }

    private function closeConnections(bool $detachedOnly = false): void
    {
        $callbacks = [];
        foreach (clone $this->connections as $scope) {
            $response = $this->connections[$scope]['response'];
            if (
                $detachedOnly &&
                $response->get() !== null &&
                !$response->get()->isEndPrevented()
            ) {
                continue;
            }
            $callbacks[] = static function () use ($scope, $response) {
                $res = $response->get();
                try {
                    if ($res !== null && $res->swoole()->isWritable()) {
                        $res->swoole()->end();
                    }
                } finally {
                    $scope->close(true);
                }
            };
        }
        CleanupException::run($callbacks);
    }

    public function stop(): void
    {
        $app = $this->application;
        $callbacks = [
            fn() => $this->drain(),
            fn() => $this->closeConnections(),
        ];
        if ($this->started) {
            foreach ($app->workerStopCallbacks() as $hook) {
                $callbacks[] = fn() => $hook($app->worker, $app);
            }
            $callbacks = [
                ...$callbacks,
                ...array_reverse($this->cleanup),
                fn() => $app->container()->stopWorker(),
            ];
        }
        $this->cleanup = [];
        $this->started = false;
        try {
            $app->within(fn() => CleanupException::run($callbacks));
        } finally {
            $app->worker = null;
        }
    }

    public function activeConnections(): int
    {
        return count($this->connections) +
            $this->application->websockets()->connections()->count();
    }

    public function isRunning(): bool
    {
        return $this->started && !$this->draining;
    }
}
