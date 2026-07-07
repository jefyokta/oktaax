<?php

namespace Oktaax\Core;

use Swoole\Timer;

class Worker
{
    public string $name;
    public int $pid;
    public function __construct(public int $id, private $taskWorker = false)
    {
        $this->pid = posix_getpid();
        $this->name = ($this->taskWorker ? "taskworker" : "worker") . "." . $id;
    }

    public function isTaskWorker()
    {

        return $this->taskWorker;
    }

    public function tick(int $ms, callable $callback)
    {
        return Timer::tick($ms, $callback);
    }
};
