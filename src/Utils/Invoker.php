<?php

namespace Oktaax\Utils;

use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use JefyOkta\PhpPromise\Promise;

final class Invoker
{
    /**
     * Typed context objects available for the next invocation.
     *
     * @var array<class-string, object>
     */
    private array $context = [];
    /**
     * Positional fallback values for the next invocation.
     *
     * @var list<mixed>
     */
    private array $positional = [];

    /**
     * Cached callback reflection keyed by callable identity.
     *
     * @var array<string, \ReflectionFunctionAbstract>
     */
    private static array $cache = [];

    /**
     * Optional custom parameter resolver for the next invocation.
     *
     * @var (\Closure(\ReflectionParameter, array<class-string, object>, list<mixed>): mixed)|null
     */
    private ?\Closure $resolver = null;

    public function addContext(object $obj): static
    {
        $this->context[$obj::class] = $obj;
        return $this;
    }

    /**
     * Provide ordered argument fallbacks for one invocation.
     *
     * @param list<mixed> $args
     */
    public function setPositional(array $args): static
    {
        $this->positional = $args;
        return $this;
    }

    /**
     * Override parameter resolution using parameter metadata, typed context, and positional fallbacks.
     *
     * @param \Closure(\ReflectionParameter, array<class-string, object>, list<mixed>): mixed $resolver
     */
    public function setResolver(\Closure $resolver): static
    {
        $this->resolver = $resolver;
        return $this;
    }

    function compile($callback)
    {
        $this->getReflection($callback);
    }

    /**
     * Resolve arguments, clear per-call context, and return the callback result without awaiting promises.
     *
     * @param callable $callback
     * @return mixed
     * @throws \Throwable When reflection or callback execution fails.
     */
    public function call(mixed $callback): mixed
    {
        $ref = $this->getReflection($callback);

        $args = [];
        $i = 0;

        foreach ($ref->getParameters() as $param) {
            if ($this->resolver) {
                $args[] = ($this->resolver)(
                    $param,
                    $this->context,
                    $this->positional,
                );
                continue;
            }

            $type = $param->getType();
            $typeName =
                $type instanceof ReflectionNamedType ? $type->getName() : null;

            if ($typeName && isset($this->context[$typeName])) {
                $args[] = $this->context[$typeName];
                continue;
            }

            if (\array_key_exists($i, $this->positional)) {
                $args[] = $this->positional[$i++];
                continue;
            }

            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }

            $args[] = null;
        }

        $this->reset();

        $result = $callback(...$args);

        if ($result instanceof Promise) {
            return $result;
        }

        return $result;
    }

    private function reset(): void
    {
        $this->context = [];
        $this->positional = [];
    }

    /**
     * Reflect a callback and cache its parameter metadata.
     *
     * @return \ReflectionFunctionAbstract
     */
    private function getReflection(mixed $callback)
    {
        if (is_array($callback)) {
            $key = $callback[0] . '::' . $callback[1];
        } elseif (\is_string($callback)) {
            $key = $callback;
        } elseif ($callback instanceof \Closure) {
            $key = spl_object_hash($callback);
        } else {
            $key = \get_class($callback) . '::__invoke';
        }

        if (!isset(self::$cache[$key])) {
            self::$cache[$key] = is_array($callback)
                ? new ReflectionMethod($callback[0], $callback[1])
                : new ReflectionFunction($callback);
        }

        return self::$cache[$key];
    }
}
