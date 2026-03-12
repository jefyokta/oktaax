<?php

namespace Oktaax\Core\Dispatcher;

use Oktaax\Http\Request;
use Oktaax\Http\Response;

class ReturnDispatcher
{
    private static array $handlers = [];

    public function register(string $type, \Closure $handler): void
    {
        static::$handlers[$type] = $handler;
    }

    public function dispatch($result, Request $req, Response $res): void
    {

        if (! $res->response->isWritable()) {
            return;
        }
        if (null == $result && $res->response->isWritable()) {
            $res->end();
            return;
        }

        if (\is_string($result)) {
            $res->end($result);
            return;
        }

        $type = \is_object($result)
            ? $result::class
            : \gettype($result);

        if (isset(static::$handlers[$type])) {

            $handler = static::$handlers[$type];

            $handler($result, $req, $res);

            return;
        }

        if (\is_array($result) || $result instanceof \Traversable) {

            $res->header("content-type", "application/json");

            $res->end(json_encode($result));

            return;
        }

        throw new \RuntimeException("Unhandled return type: $type");
    }
}
