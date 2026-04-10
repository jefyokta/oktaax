<?php

namespace Oktaax\Utils;

use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

class Invoker
{
    /** @var array<string,object> context class type => instance */
    protected array $context = [];

    /** @var array<int,mixed> positional arguments */
    protected array $positional = [];

    /** @var array<string, \ReflectionFunctionAbstract> cached reflection */
    protected static array $cache = [];

    /** @var null|callable custom param resolver */
    protected ?\Closure $resolver = null;

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

    /**
     * Set a custom resolver for callback parameters
     *
     * @param \Closure(ReflectionParameter $param, array $context, array $positional): mixed $resolver
     */
    public function setResolver(\Closure $resolver): static
    {
        $this->resolver = $resolver;
        return $this;
    }

    public function call($callback)
    {
        $ref = $this->getReflection($callback);
        $params = [];
        $i = 0;

        foreach ($ref->getParameters() as $param) {
            if ($this->resolver) {
                $params[] = ($this->resolver)($param, $this->context, $this->positional);
                continue;
            }

            $type = $param->getType();
            $typeName = $type instanceof ReflectionNamedType ? $type->getName() : null;

            if ($typeName && isset($this->context[$typeName])) {
                $params[] = $this->context[$typeName];
                continue;
            }

            if ($type?->allowsNull()) {
                $params[] = null;
                continue;
            }

            if (isset($this->positional[$i])) {
                $params[] = $this->positional[$i];
                $i++;
                continue;
            }

            if ($param->isDefaultValueAvailable()) {
                $params[] = $param->getDefaultValue();
                continue;
            }

            $params[] = null;
        }

        $this->context = [];
        $this->positional =[];

        $result = $callback(...$params);
        return $result;
    }

    protected function getReflection($callback)
    {
        if (is_string($callback)) {
            $key = $callback;
        } elseif (is_array($callback)) {
            $key = $callback[0] . '::' . $callback[1];
        } elseif ($callback instanceof \Closure) {
            $key = spl_object_id($callback);
        } elseif (is_object($callback)) {
            $key = get_class($callback) . '::__invoke';
        }

        if (!isset(self::$cache[$key])) {
            self::$cache[$key] =\is_array($callback)
                ? new ReflectionMethod($callback[0], $callback[1])
                : new ReflectionFunction($callback);
        }

        return self::$cache[$key];
    }
}
