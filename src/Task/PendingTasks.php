<?php

namespace Oktaax\Task;

use Swoole\Coroutine\Channel;

final class PendingTasks
{
    /**
     * Pending correlation IDs mapped to local channels; entries are forgotten on every exit path.
     *
     * @var array<string, \Swoole\Coroutine\Channel>
     */
    private array $channels = [];
    public function link(string $id, Channel $channel): void
    {
        $this->channels[$id] = $channel;
    }
    public function forget(string $id): void
    {
        unset($this->channels[$id]);
    }
    public function count(): int
    {
        return count($this->channels);
    }

    public function resolve(string $id, mixed $result): void
    {
        $channel = $this->channels[$id] ?? null;
        unset($this->channels[$id]);
        if ($channel !== null) {
            $channel->push($result, 0.001);
        }
    }

    public function close(): void
    {
        $channels = $this->channels;
        $this->channels = [];
        foreach ($channels as $channel) {
            $channel->close();
        }
    }
}
