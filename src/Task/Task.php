<?php

namespace Oktaax\Task;

use Oktaax\Application\ApplicationContext;

/** Convenience facade; pending state belongs to the executing application's worker. */
final class Task
{
    /**
     * Dispatch without waiting; this is not a durable queue.
     *
     * @param class-string<\Oktaax\Task\Taskable> $task
     * @param array<string, mixed> $payload
     * @throws \LogicException When the active runtime or worker cannot dispatch tasks.
     */
    public static function dispatch(string $task, array $payload = []): void
    {
        ApplicationContext::current()->tasks()->dispatch($task, $payload);
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
    public static function await(
        string $task,
        array $payload = [],
        ?float $timeout = -1,
    ): mixed {
        return ApplicationContext::current()
            ->tasks()
            ->await($task, $payload, $timeout);
    }
}
