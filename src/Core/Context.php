<?php

namespace Oktaax\Core;

use Swoole\Coroutine;

class Context
{
    public function set(string $key, mixed $value): void
    {
        Coroutine::getContext()[$key] = $value;
    }

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
        $this->remove("request");
        $this->remove("response");
    }
}
