<?php

namespace Oktaax\Core\Task;

use Oktaax\Core\Application;
use Oktaax\Core\Task\Awaitable;
use RuntimeException;
use Swoole\Coroutine\Channel;

/**
 * @method static void dispatch(\Closure $closure)
 */
class Task
{

    /**
     * Dispatch a task to the task worker.
     *
     * @param class-string $task
     * @param array $payload
     */
    public static function dispatch(string $task, array $payload = []): void
    {

        if (!is_subclass_of($task, Taskable::class)) {
            throw new RuntimeException(
                \sprintf('Task [%s] must subclass of %s', $task, Taskable::class)
            );
        }

        Application::server()->task([
            'class'   => $task,
            'payload' => $payload,
        ]);
    }


    /**
     * Await a task result from the task worker.
     *
     *
     * @template T
     * @param class-string<Taskable<T>> $task
     * @param array<string,mixed> $payload the key-value of array will be assign to the class public property
     * @param float|null $timeout Timeout in seconds.
     * @return T
     * @throws \RuntimeException|\Throwable
     */
    public static function await(string $task, array $payload = [], ?float $timeout = -1): mixed
    {
        if (!is_subclass_of($task, Taskable::class)) {
            throw new RuntimeException(
                \sprintf('Task [%s] must implement %s', $task, Taskable::class)
            );
        }

        $channel = new Channel(1);

        $taskId = Application::server()->task([
            'class'   => $task,
            'payload' => $payload,
        ]);

        Bridge::link($taskId, $channel);

        $result = $channel->pop($timeout);

        if ($result['exception']['class']) {
            $ex = $result['exception'];
            throw new $ex['class']($ex['message'], $ex['code']);
        }

        return $result['result'];
    }
}
