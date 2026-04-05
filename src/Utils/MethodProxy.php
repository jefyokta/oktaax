<?php

namespace Oktaax\Utils;

/**
 * Proxy to call methods dynamically on a target class or object.
 *
 * @template T of object
 * @mixin T
 */
class MethodProxy
{
    /**
     * @var class-string<T>|T
     */
    protected string|object $target;

    /**
     * @param class-string<T>|T $target
     * @return T
     */
    public function __construct(string|object $target)
    {
        $this->target = $target;
    }

    public function __call(string $name, array $arguments)
    {
        if (\is_object($this->target)) {
            return $this->target->$name(...$arguments);
        }

        return $this->target::$name(...$arguments);
    }
}