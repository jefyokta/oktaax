<?php

namespace Oktaax\Trait;

use Error;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;

trait Overloadable
{
    /**
     * @var class-string<OverloadClass>[]
     */
    protected static array $classes = [];

    /**
     * Register one or more classes to be used for dynamic method resolution.
     */
    public static function classRegister(string ...$classes): void
    {

        static::$classes = array_merge(static::$classes, $classes);
    }

    /**
     * Magic method to dynamically call methods from registered classes.
     */
    public function __call(string $name, array $arguments)
    {
        if ($name == "singleton") {
            throw new Error("Cannot call singleton method via overload!");
        }
        foreach (static::$classes as $className) {

            $reflection = new ReflectionClass($className);

            if (!$reflection->hasMethod($name)) {
                continue;
            }

            $method = $reflection->getMethod($name);
            $parameters = $method->getParameters();

            if (count($parameters) !== count($arguments)) {
                continue;
            }

            $isCompatible = true;
            foreach ($parameters as $index => $param) {
                if ($param->hasType()) {
                    $expectedTypes = $this->getParameterTypes($param->getType());
                    $actualType = $this->normalizeType(gettype($arguments[$index]));

                    if (!in_array($actualType, $expectedTypes, true)) {
                        $isCompatible = false;
                        break;
                    }
                }
            }

            if (!$isCompatible) {
                continue;
            }
            /**
             * @var \Oktaax\Contracts\OverloadClass
             */
            $instance = $reflection->newInstance();

            return $method->invokeArgs($instance->singleton($this), $arguments);
        }

        throw new Error("Call to undefined or incompatible method `$name` in " . static::class);
    }

    /**
     * Normalize PHP internal gettype() strings to match ReflectionType naming.
     */
    protected function normalizeType(string $type): string
    {
        return match ($type) {
            'boolean' => 'bool',
            'integer' => 'int',
            'double'  => 'float',
            'NULL'    => 'null',
            'object'  => 'object',
            default   => $type,
        };
    }

    /**
     * Extract possible types from a ReflectionType (supports union types).
     */
    protected function getParameterTypes(\ReflectionType $type): array
    {
        if ($type instanceof ReflectionNamedType) {
            return [$type->getName()];
        }

        if ($type instanceof ReflectionUnionType) {
            return array_map(fn($t) => $t->getName(), $type->getTypes());
        }

        return [];
    }
}
