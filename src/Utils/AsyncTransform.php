<?php

namespace Oktaax\Utils;

use Oktaax\Attributes\Async;
use Oktaax\Core\Promise\Promise;
use Throwable;

class AsyncTransform
{
    private static array $cache = [];

    public static function hasAsyncAttribute($callback): bool
    {
        try {
            $key = self::resolveKey($callback);

            if (isset(self::$cache[$key])) {
                return self::$cache[$key];
            }

            $ref = self::reflect($callback);

            $result = !empty($ref->getAttributes(Async::class));

            return self::$cache[$key] = $result;
        } catch (Throwable) {
            return false;
        }
    }


    private static function resolveKey($callback): string
    {
        if (is_array($callback)) {
            return $callback[0] . '::' . $callback[1];
        }

        if (is_string($callback) && str_contains($callback, '::')) {
            return $callback;
        }

        if (is_object($callback)) {
            return spl_object_hash($callback);
        }

        return (string) $callback;
    }

    private static function reflect($callback)
    {
        if (is_array($callback)) {
            return new \ReflectionMethod($callback[0], $callback[1]);
        }

        if (is_string($callback) && str_contains($callback, '::')) {
            [$class, $method] = explode('::', $callback);
            return new \ReflectionMethod($class, $method);
        }

        return new \ReflectionFunction($callback);
    }


}
