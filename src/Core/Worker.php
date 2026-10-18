<?php

namespace Oktaax\Core;

use Closure;
use Oktaax\Event\EventEmitter;
use Oktaax\Event\WorkerMessagedEvent;
use Swoole\Http\Server;
use Swoole\Timer;

use function Oktaax\Utils\app;

class Worker
{
    public string $name;

    public int $pid;



    /**
     * Handle the Swoole pipeMessage event.
     *
     * @var (Closure(WorkerMessagedEvent $e): void)|null
     */
    public ?Closure $onMessage = null;


    public ?Closure $onError = null;

    /**
     * Create a worker instance.
     *
     * @param int $id The worker ID.
     * @param bool $taskWorker Whether this is a task worker.
     */
    public function __construct(
        public int $id,
        readonly bool $taskWorker = false,
    ) {
        $this->pid = posix_getpid();
        $this->name = ($this->taskWorker ? 'taskworker' : 'worker') . '.' . $id;


        swoole_set_process_name('php ' . $this->name);
    }

    /**
     * Determine whether this instance represents a task worker.
     */
    public function isTaskWorker(): bool
    {
        return $this->taskWorker;
    }

    /**
     * Register a recurring timer.
     *
     * @param int $ms The timer interval in milliseconds.
     * @param callable $callback The callback executed on each tick.
     * @return int|false The timer ID, or false on failure.
     */
    public function tick(int $ms, callable $callback): int|false
    {
        return Timer::tick($ms, $callback);
    }

    /**
     * Send a message to another worker or broadcast it to all other workers.
     *
     * When a target ID is provided, the message is sent only to that worker.
     * Otherwise, it is broadcast to all regular workers except the current
     * worker.
     *
     * @param mixed $data The message payload.
     * @param int|null $toWorkerId The target worker ID, or null to broadcast.
     */
    public function send(mixed $data, ?int $toWorkerId = null, ?bool $includeTaskWorker = false): void
    {
        $server = app()->container(Server::class);

        if ($toWorkerId !== null) {
            $server->sendMessage($data, $toWorkerId);

            return;
        }
        $workers = $includeTaskWorker ? [...$this->workers(), ...$this->taskWorkers()] : $this->workers();
        foreach ($workers as $worker) {
            $server->sendMessage($data, $worker['id']);
        }
    }

    /**
     * Determine whether the given worker ID represents this instance.
     *
     * @param int $id The worker ID.
     * @param bool $taskWorker Whether the target is a task worker.
     */
    public function isSelf(int $id, bool $taskWorker = false): bool
    {
        return $this->id === $id
            && $this->taskWorker === $taskWorker;
    }

    /**
     * Get all regular workers except the current worker.
     *
     * @return list<array{id: int, type: 'worker'}>
     */
    public function workers(): array
    {
        $workerCount = app()->config('server.worker_num', 1);
        $workers = [];

        for ($id = 0; $id < $workerCount; $id++) {
            if ($this->isSelf($id)) {
                continue;
            }

            $workers[] = [
                'id' => $id,
                'type' => 'worker',
            ];
        }

        return $workers;
    }

    /**
     * Get all task workers except the current worker.
     *
     * Task worker IDs start after the regular worker ID range.
     *
     * @return list<array{id: int, type: 'taskWorker'}>
     */
    public function taskWorkers(): array
    {
        $workerCount = app()->config('server.worker_num', 1);
        $taskWorkerCount = app()->config('server.task_worker_num', 0);
        $workers = [];

        for ($i = 0; $i < $taskWorkerCount; $i++) {
            $id = $workerCount + $i;

            if ($this->isSelf($id, true)) {
                continue;
            }

            $workers[] = [
                'id' => $id,
                'type' => 'taskWorker',
            ];
        }

        return $workers;
    }

    /**
     * Get all workers except the current worker, grouped by type.
     *
     * @return array{
     *     worker: list<array{id: int, type: 'worker'}>,
     *     taskWorker: list<array{id: int, type: 'taskWorker'}>
     * }
     */
    public function others(): array
    {
        return [
            'worker' => $this->workers(),
            'taskWorker' => $this->taskWorkers(),
        ];
    }
}
