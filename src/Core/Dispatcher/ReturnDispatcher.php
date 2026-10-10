<?php

namespace Oktaax\Core\Dispatcher;

use Generator;
use JefyOkta\PhpPromise\Promise;
use Oktaax\Contracts\Jsonable;
use Oktaax\Core\Application;
use Oktaax\Http\Request;
use Oktaax\Http\Response;
use Oktaax\Http\Support\SSEResponse;
use Stringable;



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
        if ($result instanceof Generator) {
            foreach ($result as $content) {
                if (
                    $content === null ||
                    \is_scalar($content) ||
                    $content instanceof Stringable
                ) {
                    $res->write((string) $content);
                    continue;
                }

                throw new \RuntimeException(
                    "Unhandled generator content type: " .
                        (\is_object($content) ? $content::class : \gettype($content))
                );
            }

            $res->end($result->getReturn());
            return;
        }

        if (\is_array($result) || $result instanceof \Traversable) {
            $res->header("content-type", "application/json");
            $res->end(json_encode($result));
            return;
        }
        if ($result instanceof Jsonable) {
            $res->header("content-type", "application/json");
            $res->end(json_encode($result->toJson()));
            return;
        }
        if ($result instanceof Promise) {
            $this->dispatch(await($result), $req, $res);
            return;
        }
        if ($result instanceof SSEResponse) {
            $result->start($res);

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
