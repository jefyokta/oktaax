<?php

namespace Oktaax\Http;

use Closure;
use Oktaax\Contracts\Middleware;
use Oktaax\Utils\AsyncTransform;

final class CallableWrapper
{
    private mixed $callable;
    private string $key;
    private bool $isAsync = false;

    public function __construct(mixed $callable, private bool $isMiddleware = false)
    {
        $this->normalize($callable);

        $this->isAsync = AsyncTransform::hasAsyncAttribute($this->callable);

     
    }

    private function normalize(mixed $callable): void
    {
        if (\is_string($callable) && is_subclass_of($callable, Middleware::class)) {
            $instance = new $callable();
            $this->callable = [$instance, 'handle'];
            $this->key = (string)$callable . '::handle';
            return;
        }

        if (\is_object($callable) && $callable instanceof Middleware) {
            $this->callable = [$callable, 'handle'];
            $this->key = get_class($callable) . '::handle';
            return;
        }

        if ($callable instanceof Closure) {
            $this->callable = $callable;
            $this->key = spl_object_id($callable);
            return;
        }

        if (is_array($callable)) {
            $this->callable = $callable;
            $this->key = $callable[0] . '::' . $callable[1];
            return;
        }

        throw new \InvalidArgumentException("Invalid callable");
    }

    public function isAsync(): bool
    {
        return $this->isAsync;
    }

    public function isMiddleware(): bool
    {
        return $this->isMiddleware;
    }

    public function getCallable(): mixed
    {
        return $this->callable;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    

    public function __invoke(...$args)
    {
        return ($this->callable)(...$args);
    }
}
