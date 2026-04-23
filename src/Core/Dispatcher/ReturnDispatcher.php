<?php

namespace Oktaax\Core\Dispatcher;

use Oktaax\Console;
use Oktaax\Core\Application;
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

    public function dispatch(mixed $result, Request $req, Response $res): void
    {
        if (!$res->isWritable()) {
            return;
        }

        // Fast path for string responses (most common) - no headers needed
        if (\is_string($result)) {
            $res->end($result);
            return;
        }

        if ($result === null) {
            if (Application::context()->get("__async")) {
                return;
            }
            $res->header("x-no-content", "1");
            $res->end();
            return;
        }

        if (\is_array($result) || $result instanceof \Traversable) {
            $res->header("content-type", "application/json");
            $res->end(json_encode($result));
            return;
        }

        $type = \is_object($result) ? $result::class : \gettype($result);

        if (isset(self::$handlers[$type])) {
            (self::$handlers[$type])($result, $req, $res);
            return;
        }

        throw new \RuntimeException("Unhandled return type: $type");
    }
}
