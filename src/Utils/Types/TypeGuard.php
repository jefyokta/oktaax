<?php

namespace Oktaax\Utils\Types;

use Oktaax\Utils\Undefined;

class TypeGuard
{
    /**
     * Extension point for specialized guards; the base implementation imposes no additional constraint.
     *
     * @throws \TypeError When an overriding guard rejects the value.
     */
    public function satisfies(string $varName, mixed $value): void {}

    /**
     * Require at least one primitive/class type match, or delegate to the first specialized guard.
     *
     * @throws \TypeError When no supplied constraint accepts the value.
     */
    public static function assert(
        string $varName,
        mixed $value,
        string|TypeGuard ...$types,
    ): void {
        foreach ($types as $type) {
            if ($type instanceof TypeGuard && $type::class !== self::class) {
                $type->satisfies($varName, $value);
                return;
            }

            $valid = match ($type) {
                'mixed' => true,
                'null' => $value === null,
                'int' => \is_int($value),
                'string' => \is_string($value),
                'bool' => \is_bool($value),
                'float' => \is_float($value),
                'array' => \is_array($value),
                'callable' => \is_callable($value),
                'object' => \is_object($value),
                Undefined::class => $value === null ||
                    $value instanceof Undefined,
                default => class_exists($type) && $value instanceof $type,
            };

            if ($valid) {
                return;
            }
        }

        throw new \TypeError(
            sprintf(
                "Property '%s' must be of type %s, but %s given.",
                $varName,
                implode(
                    '|',
                    array_map(
                        fn($t) => $t instanceof TypeGuard ? \get_class($t) : $t,
                        $types,
                    ),
                ),
                get_debug_type($value),
            ),
        );
    }
}
