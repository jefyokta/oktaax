<?php

namespace Oktaax\Core\Task;

use Swoole\Coroutine\Channel;

class Bridge
{
    /** @var array<string, Channel> */
    private static array $channels = [];

    public static function link(string $id, Channel $channel): void
    {
        self::$channels[$id] = $channel;
    }

    public static function resolve(string $id, mixed $result): void
    {
        if (!isset(self::$channels[$id])) {
            return;
        }

        self::$channels[$id]->push($result);

        unset(self::$channels[$id]);
    }

    public static function forget(string $id): void
    {
        unset(self::$channels[$id]);
    }
}