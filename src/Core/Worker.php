<?php

namespace Oktaax\Core;

use Swoole\Http\Server;
use Swoole\Timer;

use function Oktaax\Utils\app;

class Worker
{
    public string $name;
    public int $pid;
    public function __construct(public int $id, private $taskWorker = false)
    {
        $this->pid = posix_getpid();
        $this->name = ($this->taskWorker ? "taskworker" : "worker") . "." . $id;
        swoole_set_process_name("php " . $this->name);
    }

    public function isTaskWorker()
    {

        return $this->taskWorker;
    }

    public function tick(int $ms, callable $callback)
    {
        return Timer::tick($ms, $callback);
    }

    public function send(mixed $data, $toWorkerId = null)
    {

        $server = app()->container(Server::class);

        if (null !== $toWorkerId) {

            $server->sendMessage($data, $toWorkerId);
            return;
        }
        $totalWorkers = app()->config("server.worker_num", 1);


        for ($i = 0; $i < $totalWorkers; $i++) {
            if ($i === $this->id) {
                continue;
            }
            $server->sendMessage($data, $i);
        }
    }
};
