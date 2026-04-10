<?php

namespace Oktaax\Core\Dispatcher;

class ExceptionDispatcher
{
    private static array $handlers = [];

    public function register(string $exception, \Closure $handler)
    {
        self::$handlers[$exception] = $handler;
    }

    public function dispatch(\Throwable $e)
    {
        $class = $e::class;

        if (isset(self::$handlers[$class])) {

            $handler = self::$handlers[$class];

            $handler($e);

            return;
        }

        throw $e;
    }
}
