<?php

namespace Oktaax\Core;

use Oktaax\Http\Request;
use RuntimeException;
use Swoole\Coroutine;
use Oktaax\Http\Response;

/**
 * @property  ?Request $request
 * @property ?Response $response
 */
class Context
{

    /**
     * @template T
     * Summary of set
     * @param class-string<T> $key
     * @param T $value
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        Coroutine::getContext()[$key] = $value;
    }

    /**
     * @template T
     * Summary of get
     * @param class-string<T> $key
     * @param ?int $cid
     * @return T
     */
    public function get(string $key, ?int $cid = null): mixed
    {
        if ($cid===null) {
          return  Coroutine::getContext()[$key] ?? null;
        }
        return Coroutine::getContext($cid)[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset(Coroutine::getContext()[$key]);
    }
    public function remove(string $key): void
    {
        Coroutine::getContext()->offsetUnset($key);
    }
    public function destroy()
    {
        $ctx = Coroutine::getContext();
        unset($ctx[Request::class], $ctx[Response::class]);
    }

    function request()
    {
        return $this->get(Request::class);
    }
    function response()
    {
        return $this->get(Response::class);
    }

    public function __get(string $name)
    {
        if ($name === "request" || $name === "response") {
            return \call_user_func([$this, $name]);
        }

        throw new RuntimeException("undefined property $name");
    }
}
