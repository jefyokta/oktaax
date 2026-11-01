<?php

namespace Oktaax\Utils;

use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;

class Reflection
{
    /**
     * Reflect the supplied closure, function name, or callable method reference.
     *
     * @throws \ReflectionException When the target function or method is unavailable.
     */
    public static function callable(callable $cb): ReflectionFunctionAbstract
    {
        if (\is_array($cb)) {
            return new ReflectionMethod($cb[0], $cb[1]);
        }

        if (\is_string($cb) && \str_contains($cb, '::')) {
            return new ReflectionMethod($cb);
        }

        return new ReflectionFunction($cb);
    }
}
