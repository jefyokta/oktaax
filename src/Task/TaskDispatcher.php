<?php

namespace Oktaax\Task;

use Oktaax\Application;
use Oktaax\Exception\TaskFailedException;
use Oktaax\Exception\TaskTimeoutException;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

/**
 * Dispatch serializable task payloads and own pending result channels for one application worker.
 *
 * @phpstan-type RemoteError array{class: class-string<\Throwable>|null, message: string|null, code: int, handled: bool, file?: string, line?: int, trace?: string}
 * @phpstan-type TaskResult array{class: class-string<\Oktaax\Task\Taskable>, request_id: string|null, result: mixed, exception: RemoteError}
 */
final class TaskDispatcher
{
    private PendingTasks $pending;
    public function __construct(private Application $application)
    {
        $this->pending = new PendingTasks();
    }
    public function pending(): PendingTasks
    {
        return $this->pending;
    }

    /**
     * Dispatch without waiting; this is not a durable queue.
     *
     * @param class-string<\Oktaax\Task\Taskable> $task
     * @param array<string, mixed> $payload
     * @throws \LogicException When the active runtime or worker cannot dispatch tasks.
     */
    public function dispatch(string $task, array $payload = []): void
    {
        $this->send($task, $payload);
    }

    private function send(
        string $task,
        array $payload,
        ?string $requestId = null,
    ): int {
        if (!is_subclass_of($task, Taskable::class)) {
            throw new \InvalidArgumentException(
                'Task must implement ' . Taskable::class,
            );
        }
        if (
            !$this->application->workers()->isRunning() ||
            !$this->application->runtime()?->supportsTasks() ||
            $this->application->config('server.task_worker_num', 0) < 1
        ) {
            throw new \LogicException(
                'Task workers are unavailable in the active runtime.',
            );
        }
        $id = $this->application->nativeServer()->task([
            'class' => $task,
            'payload' => $payload,
            'request_id' => $requestId,
        ]);
        if ($id === false) {
            throw new \RuntimeException('Failed to dispatch task.');
        }
        return $id;
    }

    /**
     * Wait in a coroutine for a correlated result; timeout is in seconds, negative/null means no deadline.
     *
     * @param class-string<\Oktaax\Task\Taskable> $task
     * @param array<string, mixed> $payload
     * @throws \Oktaax\Exception\TaskTimeoutException When the local wait expires; remote execution continues.
     * @throws \Oktaax\Exception\TaskFailedException When a remote failure was not handled.
     * @throws \LogicException When the active runtime or worker cannot dispatch tasks.
     */
    public function await(
        string $task,
        array $payload = [],
        ?float $timeout = -1,
    ): mixed {
        if (Coroutine::getCid() < 0) {
            throw new \LogicException(
                'Awaiting a task requires an active coroutine.',
            );
        }
        $key = bin2hex(random_bytes(16));
        $channel = new Channel(1);
        $this->pending->link($key, $channel);
        try {
            $this->send($task, $payload, $key);
            $result = $channel->pop($timeout ?? -1);
            if ($result === false) {
                if ($channel->errCode === SWOOLE_CHANNEL_TIMEOUT) {
                    throw new TaskTimeoutException('Task timed out.');
                }
                throw new \RuntimeException(
                    'Task wait was cancelled because its worker scope closed.',
                );
            }
            if (
                ($result['exception']['class'] ?? null) !== null &&
                !($result['exception']['handled'] ?? false)
            ) {
                throw new TaskFailedException($result['exception']);
            }
            return $result['result'] ?? null;
        } finally {
            $this->pending->forget($key);
        }
    }
}
