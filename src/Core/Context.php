<?php

namespace Oktaax\Core;

use Oktaax\Http\Request;
use Swoole\Coroutine;
use Oktaax\Http\Response;

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
     * @param mixed $default
     * @return T
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Coroutine::getContext()[$key] ?? $default;
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
        $this->remove(Request::class);
        $this->remove(Response::class);
    }

    function request()
    {
        return $this->get(Request::class);
    }
    function response()
    {
        return $this->get(Response::class);
    }
}
