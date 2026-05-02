<?php

namespace Oktaax\Core;

use Closure;

class Container
{
    private static $container = [];
    private static $aliases = [];
    private function __construct() {}
    /**
     * Register a service into the container.
     *
     * @template T of object
     *
     * @param class-string<T> $service
     * @param T|\Closure():T $concrete
     *
     * @return void
     */
    public static function register(string $service, $concrete): void
    {
        $instance = $concrete instanceof Closure ? $concrete() : $concrete;

        if ($instance instanceof $service || is_subclass_of($instance::class, $service)) {
            self::$container[$service] = $instance;
        }
    }
    /**
     * @template T
     * @param class-string<T> $service
     * @return T
     */
    public static function get(string $service)
    {
        return self::$container[$service] ?? self::$container[self::$aliases[$service]];
    }

    public static function alias(string $alias, string $serviceKey)
    {
        self::$aliases[$alias] = $serviceKey;
    }
}
