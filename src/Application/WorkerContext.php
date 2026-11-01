<?php

namespace Oktaax\Application;

use Oktaax\Application;
use Swoole\Timer;

/** Passive worker identity plus operations supported by its runtime. */
final class WorkerContext
{
    public string $name;
    public int $pid;
    /**
     * Optional handler for raw IPC payloads that are not framework Event values.
     *
     * @var (\Closure(\Oktaax\Event\WorkerMessagedEvent): mixed)|null
     */
    public ?\Closure $onMessage = null;
    public ?\Closure $onError = null;

    public function __construct(
        public readonly int $id,
        public readonly bool $taskWorker,
        private Application $application,
    ) {
        $this->pid = getmypid();
        $this->name = ($taskWorker ? 'taskworker' : 'worker') . '.' . $id;
    }

    public function isTaskWorker(): bool
    {
        return $this->taskWorker;
    }
    public function isSelf(int $id, bool $taskWorker = false): bool
    {
        return $this->id === $id && $this->taskWorker === $taskWorker;
    }

    /**
     * Schedule a recurring worker timer in milliseconds; worker stop cancels it.
     *
     * @param callable(int): mixed $callback
     */
    public function tick(int $ms, callable $callback): int|false
    {
        $app = $this->application;
        if (!$app->workers()->isRunning()) {
            throw new \LogicException(
                'Cannot schedule worker timers while the worker is stopping.',
            );
        }
        $timer = Timer::tick(
            $ms,
            fn(...$args) => $app->within(fn() => $callback(...$args)),
        );
        if ($timer !== false) {
            $app->workers()->trackTimer($timer);
        }
        return $timer;
    }

    /**
     * Send serializable data to a worker or to peer HTTP workers; IPC delivery is asynchronous.
     *
     * @throws \LogicException When the active runtime does not support worker messaging.
     * @throws \RuntimeException When a native send fails.
     */
    public function send(
        mixed $data,
        ?int $toWorkerId = null,
        ?bool $includeTaskWorker = false,
    ): void {
        if (!$this->application->runtime()?->supportsWorkerMessages()) {
            throw new \LogicException(
                'The active runtime does not support worker messages.',
            );
        }
        $server = $this->application->nativeServer();
        $ids =
            $toWorkerId !== null
                ? [['id' => $toWorkerId]]
                : ($includeTaskWorker
                    ? [...$this->workers(), ...$this->taskWorkers()]
                    : $this->workers());
        foreach ($ids as $worker) {
            if (!$server->sendMessage($data, $worker['id'])) {
                throw new \RuntimeException('Failed to send worker message.');
            }
        }
    }

    /**
     * List peer workers of the selected kind, excluding this worker.
     *
     * @return list<array{id: int, type: 'worker'|'taskWorker'}>
     */
    public function workers(): array
    {
        return $this->workerList(false);
    }
    /**
     * List peer workers of the selected kind, excluding this worker.
     *
     * @return list<array{id: int, type: 'worker'|'taskWorker'}>
     */
    public function taskWorkers(): array
    {
        return $this->workerList(true);
    }
    /**
     * Group peer worker descriptors by HTTP worker and task worker kind.
     *
     * @return array{worker: list<array{id: int, type: 'worker'|'taskWorker'}>, taskWorker: list<array{id: int, type: 'worker'|'taskWorker'}>}
     */
    public function others(): array
    {
        return [
            'worker' => $this->workers(),
            'taskWorker' => $this->taskWorkers(),
        ];
    }

    /**
     * List peer workers of the selected kind, excluding this worker.
     *
     * @return list<array{id: int, type: 'worker'|'taskWorker'}>
     */
    private function workerList(bool $tasks): array
    {
        $server = $this->application->nativeServer();
        $count =
            $server?->setting['worker_num'] ??
            $this->application->config('server.worker_num', 1);
        $taskCount =
            $server?->setting['task_worker_num'] ??
            $this->application->config('server.task_worker_num', 0);
        $start = $tasks ? $count : 0;
        $end = $tasks ? $count + $taskCount : $count;
        $workers = [];
        for ($id = $start; $id < $end; $id++) {
            if (!$this->isSelf($id, $tasks)) {
                $workers[] = [
                    'id' => $id,
                    'type' => $tasks ? 'taskWorker' : 'worker',
                ];
            }
        }
        return $workers;
    }
}
