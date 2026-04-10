<?php

namespace Oktaax\Core\Dispatcher;

use Oktaax\Core\Promise\Promise;
use Oktaax\Http\Request;
use Oktaax\Http\Response;

use function Oktaax\Utils\await;

class ReturnDispatcher
{
    private static array $handlers = [];

    public function register(string $type, \Closure $handler): void
    {
        static::$handlers[$type] = $handler;
    }

    public function dispatch($result, Request $req, Response $res): void
    {

        if (! $res->isWritable()) {
            return;
        }
        if (null == $result && $res->isWritable()) {
            $res->header("x-no-content", 1);
            $res->end();
            return;
        }
        if ($result instanceof Promise) {
            $r = await($result);
            $this->dispatch($r, $req, $res);
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
