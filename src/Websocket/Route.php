<?php

namespace Oktaax\Websocket;


class Route
{
    protected static $eventHandlers = [];

    static function event($event, $handler)
    {
        static::$eventHandlers[$event] = $handler;
    }

    static function channel($channel)
    {
        return new static();
    }
}
