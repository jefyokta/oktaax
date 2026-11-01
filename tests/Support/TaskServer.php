<?php

namespace Tests\Support;

final class TaskServer extends \Swoole\Http\Server
{
    public array $messages = [];
    public ?\Closure $onTask = null;
    public bool $acceptTasks = true;
    public mixed $finished = null;
    public function __construct() {}
    public function task(
        mixed $data,
        int $taskWorkerIndex = -1,
        ?callable $finishCallback = null,
    ): int|false {
        if (!$this->acceptTasks) {
            return false;
        }
        $this->messages[] = $data;
        if ($this->onTask !== null) {
            ($this->onTask)($data);
        }
        return count($this->messages);
    }
    public function finish(mixed $data): bool
    {
        $this->finished = $data;
        return true;
    }
}
