<?php

namespace Oktaax\Utils;

use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use Oktaax\Http\CallableWrapper;
use Oktaax\Core\Promise\Promise;

final class Invoker
{
    private array $context = [];
    private array $positional = [];

    private static array $cache = [];

    private ?\Closure $resolver = null;

    public function addContext(object $obj): static
    {
        $this->context[$obj::class] = $obj;
        return $this;
    }

    public function setPositional(array $args): static
    {
        $this->positional = $args;
        return $this;
    }

    public function setResolver(\Closure $resolver): static
    {
        $this->resolver = $resolver;
        return $this;
    }

    public function call(mixed $callback): mixed
    {
        $ref = $this->getReflection($callback);

        $args = [];
        $i = 0;

        foreach ($ref->getParameters() as $param) {

            // custom resolver (override full DI system)
            if ($this->resolver) {
                $args[] = ($this->resolver)($param, $this->context, $this->positional);
                continue;
            }

            $type = $param->getType();
            $typeName = $type instanceof ReflectionNamedType ? $type->getName() : null;

            // DI by type hint
            if ($typeName && isset($this->context[$typeName])) {
                $args[] = $this->context[$typeName];
                continue;
            }

            // positional fallback
            if (array_key_exists($i, $this->positional)) {
                $args[] = $this->positional[$i++];
                continue;
            }

            // default value
            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }

            $args[] = null;
        }

        $this->reset();

        $result = $callback(...$args);

        // 🔥 IMPORTANT: async-aware return handling
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

    private function getReflection(mixed $callback)
    {
        if ($callback instanceof CallableWrapper) {
            $callback = $callback->getCallable();
            $key = $callback instanceof CallableWrapper
                ? $callback->getKey()
                : null;
        }

        if (is_array($callback)) {
            $key = $callback[0] . '::' . $callback[1];
        } elseif (is_string($callback)) {
            $key = $callback;
        } elseif ($callback instanceof \Closure) {
            $key = spl_object_hash($callback); // 🔥 FIXED (lebih stable dari object_id)
        } else {
            $key = get_class($callback) . '::__invoke';
        }

        if (!isset(self::$cache[$key])) {
            self::$cache[$key] = is_array($callback)
                ? new ReflectionMethod($callback[0], $callback[1])
                : new ReflectionFunction($callback);
        }

        return self::$cache[$key];
    }
}