<?php

namespace Oktaax\Application;

use Oktaax\Http\Request;
use Oktaax\Http\Response;
use RuntimeException;
use Swoole\Coroutine;

/** One namespace per application inside each coroutine. */
final class Context
{
    private string $key;

    public function __construct()
    {
        $this->key = 'oktaax.request.' . spl_object_id($this);
    }

    public function set(string $key, mixed $value): void
    {
        $context = Coroutine::getContext();
        if ($context === false || $context === null) {
            throw new RuntimeException(
                'Request context requires an active coroutine.',
            );
        }
        $values = $context[$this->key] ?? [];
        $values[$key] = $value;
        $context[$this->key] = $values;
    }

    public function get(string $key, ?int $cid = null): mixed
    {
        $context =
            $cid === null
                ? Coroutine::getContext()
                : Coroutine::getContext($cid);
        return $context !== false && $context !== null
            ? $context[$this->key][$key] ?? null
            : null;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function remove(string $key): void
    {
        $context = Coroutine::getContext();
        if (
            $context !== false &&
            $context !== null &&
            isset($context[$this->key])
        ) {
            $values = $context[$this->key];
            unset($values[$key]);
            $context[$this->key] = $values;
        }
    }

    public function destroy(): void
    {
        $context = Coroutine::getContext();
        if ($context !== false && $context !== null) {
            unset($context[$this->key]);
        }
    }

    public function request(): ?Request
    {
        return $this->get(Request::class);
    }
    public function response(): ?Response
    {
        return $this->get(Response::class);
    }

    public function __get(string $name): mixed
    {
        return match ($name) {
            'request' => $this->request(),
            'response' => $this->response(),
            default => throw new RuntimeException(
                "Undefined context property: {$name}",
            ),
        };
    }
}
