<?php

namespace Oktaax\Application;

use Closure;
use ReflectionFunction;
use ReflectionNamedType;

final class CallbackInvoker
{
    /**
     * Resolve startup parameters from typed runtime objects; untyped/string parameters receive the URL.
     *
     * @param list<object|null> $objects
     * @throws \InvalidArgumentException When a required parameter cannot be resolved.
     */
    public static function call(
        callable $callback,
        array $objects,
        string $url,
    ): mixed {
        $closure = Closure::fromCallable($callback);
        $arguments = [];
        foreach (
            (new ReflectionFunction($closure))->getParameters()
            as $parameter
        ) {
            $type = $parameter->getType();
            if (
                $type === null ||
                ($type instanceof ReflectionNamedType &&
                    $type->getName() === 'string')
            ) {
                $arguments[] = $url;
                continue;
            }
            $matched = false;
            foreach ($objects as $object) {
                if ($object === null) {
                    continue;
                }
                $types =
                    $type instanceof \ReflectionUnionType
                        ? $type->getTypes()
                        : [$type];
                foreach ($types as $candidate) {
                    if (
                        $candidate instanceof ReflectionNamedType &&
                        !$candidate->isBuiltin() &&
                        is_a($object, $candidate->getName())
                    ) {
                        $arguments[] = $object;
                        $matched = true;
                        break 2;
                    }
                }
            }
            if (!$matched) {
                if ($parameter->isDefaultValueAvailable()) {
                    $arguments[] = $parameter->getDefaultValue();
                } elseif ($parameter->allowsNull()) {
                    $arguments[] = null;
                } else {
                    throw new \InvalidArgumentException(
                        'Cannot resolve startup callback parameter: ' .
                            $parameter->getName(),
                    );
                }
            }
        }
        return $closure(...$arguments);
    }
}
