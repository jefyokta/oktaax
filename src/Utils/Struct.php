<?php

namespace Oktaax\Utils;

use ArrayAccess;
use Closure;
use Error;
use InvalidArgumentException;
use IteratorAggregate;
use JefyOkta\PhpPromise\Asynchronous;
use JsonSerializable;
use Oktaax\Console;
use Oktaax\Utils\Interfaces\CanConvertToBoolean;
use Oktaax\Utils\Types\TypeGuard;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionUnionType;
use Traversable;

/**
 * Named-property utility object with bound closures, computed getters, and optional runtime type guards.
 *
 * @template T of object
 * @mixin T
 * @implements \ArrayAccess<array-key, mixed>
 * @implements \IteratorAggregate<array-key, mixed>
 * @phpstan-type PropertyDescriptor array{value?: mixed, writable?: bool, configurable?: bool, enumerable?: bool}
 */
class Struct implements ArrayAccess, IteratorAggregate, JsonSerializable
{
    private \stdClass $attributes;

    public static bool $skipTypeCheck = false;
    //ex
    /**
     * Runtime constraints used when strict mode is enabled.
     *
     * @var array<string, string|\Oktaax\Utils\Types\TypeGuard|list<string|\Oktaax\Utils\Types\TypeGuard>>
     */
    private $propertyTypes = [];

    /**
     * Whether writes must satisfy the registered runtime property constraints.
     *
     * @var bool
     */
    private $strict = false;

    /**
     * Property names restricted by the corresponding descriptor flag.
     *
     * @var list<string>
     */
    private array $nonWritableProperties = [];

    /**
     * Property names restricted by the corresponding descriptor flag.
     *
     * @var list<string>
     */
    private array $nonConfigurableProperties = [];

    /**
     * Property names restricted by the corresponding descriptor flag.
     *
     * @var list<string>
     */
    private array $nonEnumerableProperties = [];

    private bool $frozen = false;
    private bool $extensible = true;

    /**
     * Create properties from named arguments only and bind closures/getters to this object.
     *
     * @param mixed ...$params
     * @named-arguments
     * @throws \InvalidArgumentException When a positional argument is supplied.
     */
    public function __construct(mixed ...$params)
    {
        $this->attributes = new \stdClass();

        foreach ($params as $key => $value) {
            if (is_int($key)) {
                $argumentPosition = $key + 1;
                $culpritValue = is_scalar($value)
                    ? var_export($value, true)
                    : '[' . gettype($value) . ']';

                throw new InvalidArgumentException(
                    "Missing argument name at position #{$argumentPosition}." .
                        PHP_EOL .
                        "Details: Received the value {$culpritValue} as a positional argument, but Struct requires named arguments." .
                        PHP_EOL .
                        PHP_EOL .
                        'Correct usage:' .
                        PHP_EOL .
                        "    new Struct(name: \"Jefy\", status: \"active\")",
                );
            }

            $this->attributes->$key = $this->bindValue($value);
        }
    }

    public function &__get(string $name): mixed
    {
        if (!property_exists($this->attributes, $name)) {
            $undefined = undefined;
            return $undefined;
        }

        $value = $this->attributes->$name;

        if ($value instanceof GetterProperty) {
            $value = $value->getCallback()->call($this);
            return $value;
        }

        return $this->attributes->$name;
    }
    private function setStrictly(string $name, mixed $value): void
    {
        if (!array_key_exists($name, $this->propertyTypes)) {
            throw new Error("Unknown property '{$name}'.");
        }

        $types = $this->propertyTypes[$name];
        $types = is_array($types) ? $types : [$types];

        TypeGuard::assert($name, $value, ...$types);

        $this->attributes->$name =
            $value === null || $value instanceof Undefined
                ? $value
                : $this->bindValue($value);
    }
    // private function setStrictly(string $name, mixed $value): void
    // {
    //     if (!array_key_exists($name, $this->propertyTypes)) {
    //         throw new Error("Unknown property '{$name}'.");
    //     }

    //     $types = $this->propertyTypes[$name];
    //     $types = is_array($types) ? $types : [$types];

    //     foreach ($types as $type) {

    //         if ($type === 'mixed') {
    //             $this->attributes->$name = $this->bindValue($value);
    //             return;
    //         }

    //         if ($type === 'null' && $value === null) {
    //             $this->attributes->$name = null;
    //             return;
    //         }

    //         if ($type == Undefined::class && ($value == null || $value instanceof Undefined)) {
    //             $this->attributes->$name = undefined;
    //             return;
    //         }

    //         $valid = match ($type) {
    //             'int'      => is_int($value),
    //             'string'   => is_string($value),
    //             'bool'     => is_bool($value),
    //             'float'    => is_float($value),
    //             'array'    => is_array($value),
    //             'callable' => is_callable($value),
    //             'object'   => is_object($value),
    //             default    => class_exists($type) && $value instanceof $type,
    //         };

    //         if ($valid) {
    //             $this->attributes->$name = $this->bindValue($value);
    //             return;
    //         }
    //     }

    //     throw new \TypeError(sprintf(
    //         "Property '%s' must be of type %s, but %s given.",
    //         $name,
    //         implode('|', $types),
    //         get_debug_type($value)
    //     ));
    // }

    public function __set(string $name, mixed $value): void
    {
        if ($this->strict) {
            try {
                $this->setStrictly($name, $value);
                return;
            } catch (\Throwable $th) {
                throw $th;
            }
        }
        if ($this->frozen) {
            throw new Error('Cannot modify frozen object.');
        }
        if (!$this->extensible && !property_exists($this->attributes, $name)) {
            throw new Error(
                "Cannot add property '{$name}', object is not extensible.",
            );
        }

        if (!$this->isWritable($name)) {
            throw new Error("Cannot assign to read only property '{$name}'.");
        }

        if (
            property_exists($this->attributes, $name) &&
            $this->attributes->$name instanceof GetterProperty
        ) {
            throw new Error('Getter cannot be mutated!');
        }

        $this->attributes->$name = $this->bindValue($value);
    }

    public function __isset(string $name): bool
    {
        return isset($this->attributes->$name);
    }

    public function __unset(string $name): void
    {
        if ($this->frozen) {
            throw new Error('Cannot modify frozen object.');
        }

        if (!$this->isConfigurable($name)) {
            throw new Error(
                "Cannot delete non-configurable property '{$name}'.",
            );
        }

        unset($this->attributes->$name);
    }

    /**
     * Invoke a function-valued property with the supplied positional or named arguments.
     *
     * @param array<array-key, mixed> $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        if (
            property_exists($this->attributes, $name) &&
            ($this->attributes->$name instanceof Closure ||
                $this->attributes->$name instanceof Asynchronous)
        ) {
            return ($this->attributes->$name)(...$arguments);
        }

        throw new \BadMethodCallException("Cannot find method {$name}");
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->attributes->{$offset});
    }

    public function &offsetGet(mixed $offset): mixed
    {
        return $this->__get((string) $offset);
    }
    private function bindValue(mixed $value): mixed
    {
        if ($value instanceof Closure) {
            $value = Closure::fromCallable($value);
            return $value->bindTo($this, static::class);
        }

        return $value;
    }
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            throw new InvalidArgumentException('Struct requires string keys.');
        }

        $this->__set((string) $offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->__unset((string) $offset);
    }

    /**
     * Iterate enumerable properties and their values.
     *
     * @return \Traversable<array-key, mixed>
     */
    public function getIterator(): Traversable
    {
        foreach (get_object_vars($this->attributes) as $key => $value) {
            if ($this->isEnumerable($key)) {
                yield $key => $value;
            }
        }
    }

    /**
     * Expose enumerable property values to JSON encoding.
     *
     * @return array<array-key, mixed>
     */
    public function jsonSerialize(): mixed
    {
        $result = [];

        foreach (get_object_vars($this->attributes) as $key => $value) {
            if ($this->isEnumerable($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    public function __debugInfo(): array
    {
        return $this->jsonSerialize();
    }

    public function __toString(): string
    {
        // return "[object Object]";
        return json_encode(
            $this->jsonSerialize(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
        );
    }
    public static function preventExtensions(self $object): self
    {
        $object->extensible = false;
        return $object;
    }

    public static function isExtensible(self $object): bool
    {
        return $object->extensible;
    }

    public static function seal(self $object): self
    {
        $object->extensible = false;

        foreach (get_object_vars($object->attributes) as $key => $_) {
            if (!in_array($key, $object->nonConfigurableProperties, true)) {
                $object->nonConfigurableProperties[] = $key;
            }
        }

        return $object;
    }

    public static function isSealed(self $object): bool
    {
        if ($object->extensible) {
            return false;
        }

        foreach (get_object_vars($object->attributes) as $key => $_) {
            if ($object->isConfigurable($key)) {
                return false;
            }
        }

        return true;
    }

    public static function hasOwn(self $object, string $property): bool
    {
        return property_exists($object->attributes, $property);
    }

    public static function is(mixed $value1, mixed $value2): bool
    {
        if (\gettype($value1) !== \gettype($value2)) {
            return false;
        }
        if (\is_float($value1) && \is_float($value2)) {
            if (is_nan($value1) && is_nan($value2)) {
                return true;
            }

            if ($value1 == 0.0 && $value2 == 0.0) {
                return pack('d', $value1) === pack('d', $value2);
            }
        }

        return $value1 === $value2;
    }
    /**
     * Return enumerable property keys.
     *
     * @return list<array-key>
     */
    public static function keys(self $object): array
    {
        return array_keys($object->jsonSerialize());
    }

    /**
     * Return enumerable property values in property order.
     *
     * @return list<mixed>
     */
    public static function values(self $object): array
    {
        return array_values($object->jsonSerialize());
    }

    /**
     * Return the enumerable associative property map, rather than a list of key/value tuples.
     *
     * @return array<array-key, mixed>
     */
    public static function entries(self $object): array
    {
        return $object->jsonSerialize();
    }

    public function clone(): self
    {
        return clone $this;
    }

    public static function assign(self $target, self ...$sources): self
    {
        foreach ($sources as $source) {
            foreach (self::entries($source) as $key => $value) {
                $target->$key = $value;
            }
        }

        return $target;
    }

    /**
     * Create properties from iterable keys and values; entries are not [key, value] tuples.
     *
     * @param iterable<array-key, mixed> $entries
     * @return self<object>
     */
    public static function fromEntries(iterable $entries = []): self
    {
        $object = new self();

        foreach ($entries as $key => $value) {
            $object->$key = $value;
        }

        return $object;
    }

    public static function isFrozen(self $object): bool
    {
        return $object->frozen;
    }

    public static function freeze(self $object): self
    {
        $object->frozen = true;

        foreach (get_object_vars($object->attributes) as $key => $_) {
            if (!in_array($key, $object->nonWritableProperties, true)) {
                $object->nonWritableProperties[] = $key;
            }

            if (!in_array($key, $object->nonConfigurableProperties, true)) {
                $object->nonConfigurableProperties[] = $key;
            }
        }

        return $object;
    }

    /**
     * Apply a value and descriptor flags; omitted flags default to false.
     *
     * @param self<object>|PropertyDescriptor $descriptor
     */
    public static function defineProperty(
        self $object,
        string $property,
        self|array $descriptor,
    ): void {
        $value = $descriptor['value'] ?? null;
        $writable = $descriptor['writable'] ?? false;
        $configurable = $descriptor['configurable'] ?? false;
        $enumerable = $descriptor['enumerable'] ?? false;

        $object->attributes->$property = $object->bindValue($value);

        if (
            !$writable &&
            !\in_array($property, $object->nonWritableProperties, true)
        ) {
            $object->nonWritableProperties[] = $property;
        }

        if (
            !$configurable &&
            !\in_array($property, $object->nonConfigurableProperties, true)
        ) {
            $object->nonConfigurableProperties[] = $property;
        }

        if (
            !$enumerable &&
            !\in_array($property, $object->nonEnumerableProperties, true)
        ) {
            $object->nonEnumerableProperties[] = $property;
        }
    }

    /**
     * Apply named property descriptors.
     *
     * @param self<object>|array<string, self<object>|PropertyDescriptor> $properties
     */
    public static function defineProperties(
        self $object,
        self|array $properties,
    ): void {
        foreach ($properties as $key => $descriptor) {
            self::defineProperty($object, $key, $descriptor);
        }
    }

    private function isWritable(string $key): bool
    {
        return !\in_array($key, $this->nonWritableProperties, true);
    }

    private function isConfigurable(string $key): bool
    {
        return !\in_array($key, $this->nonConfigurableProperties, true);
    }

    private function isEnumerable(string $key): bool
    {
        return !\in_array($key, $this->nonEnumerableProperties, true);
    }
    /**
     * Check property and method compatibility with a reflected class contract; optionally report errors through a callback.
     *
     * @param Struct<object> $object
     * @param class-string<TContract> $contract
     * @param (\Closure(\Error): mixed)|null $onError
     * @template TContract of object
     * @throws \Error When the object violates its contract and no error callback handles it.
     * @throws \InvalidArgumentException When the contract class is unavailable.
     */
    public static function satisfies(
        Struct $object,
        string $contract,
        ?Closure $onError = null,
    ): bool {
        if (self::$skipTypeCheck) {
            return true;
        }
        if (!class_exists($contract) && !interface_exists($contract)) {
            throw new InvalidArgumentException(
                "Contract '{$contract}' does not exist.",
            );
        }

        $reflection = new \ReflectionClass($contract);

        $fail = function (string $message) use ($onError): bool {
            $error = new Error($message);

            if ($onError) {
                $onError($error);
                return false;
            }

            throw $error;
        };

        $isRequired = function (\ReflectionType|null $type): bool {
            if ($type === null) {
                return true;
            }

            if ($type instanceof ReflectionNamedType) {
                if ($type->allowsNull()) {
                    return false;
                }

                return $type->getName() !== Undefined::class;
            }

            if ($type instanceof ReflectionUnionType) {
                foreach ($type->getTypes() as $namedType) {
                    /**
                     * @disregard
                     */
                    $name = $namedType->getName();

                    if ($name === 'null' || $name === Undefined::class) {
                        return false;
                    }
                }

                return true;
            }

            return true;
        };

        $matchesType = function (
            mixed $value,
            ReflectionNamedType $type,
        ): bool {
            $expectedType = $type->getName();

            return match ($expectedType) {
                'int' => is_int($value),
                'string' => is_string($value),
                'bool' => is_bool($value),
                'float' => is_float($value),
                'array' => is_array($value),
                'null' => $value === null,
                'mixed' => true,
                default => $value instanceof $expectedType,
            };
        };

        foreach (
            $reflection->getProperties(\ReflectionProperty::IS_PUBLIC)
            as $property
        ) {
            $name = $property->getName();
            $type = $property->getType();

            $required = $isRequired($type);

            if (!self::hasOwn($object, $name)) {
                if ($required) {
                    return $fail(
                        "Struct does not satisfy contract '{$contract}'. Missing property '{$name}'.",
                    );
                }

                continue;
            }

            $value = $object->$name;

            if ($type === null) {
                continue;
            }

            $valid = false;

            if ($type instanceof ReflectionNamedType) {
                $valid = $matchesType($value, $type);
            } elseif ($type instanceof ReflectionUnionType) {
                foreach ($type->getTypes() as $namedType) {
                    if ($matchesType($value, $namedType)) {
                        $valid = true;
                        break;
                    }
                }
            } elseif ($type instanceof ReflectionIntersectionType) {
                $valid = true;

                foreach ($type->getTypes() as $namedType) {
                    if (!$matchesType($value, $namedType)) {
                        $valid = false;
                        break;
                    }
                }
            }

            if (!$valid) {
                $expected = match (true) {
                    $type instanceof ReflectionNamedType => $type->getName(),
                    $type instanceof ReflectionUnionType => implode(
                        '|',
                        array_map(
                            fn(ReflectionNamedType $t): string => $t->getName(),
                            $type->getTypes(),
                        ),
                    ),
                    $type instanceof \ReflectionIntersectionType => implode(
                        '&',
                        array_map(
                            fn(ReflectionNamedType $t): string => $t->getName(),
                            $type->getTypes(),
                        ),
                    ),
                    default => 'unknown',
                };

                return $fail("Property '{$name}' must be of type {$expected}.");
            }
        }

        return true;
    }

    static function create(Struct|array $source)
    {
        return $source instanceof self
            ? $source->clone()
            : self::fromEntries($source);
    }

    /**
     * Create a Struct whose subsequent writes are checked against the supplied property constraints.
     *
     * @param array<string, string|\Oktaax\Utils\Types\TypeGuard|list<string|\Oktaax\Utils\Types\TypeGuard>> $propertyTypes
     * @param mixed ...$properties
     * @return static<object>
     */
    static function strict(array $propertyTypes, mixed ...$properties)
    {
        $object = new static();
        $object->propertyTypes = $propertyTypes;
        $object->strict = true;

        foreach ($properties as $key => $value) {
            $object->$key = $value;
        }
        return $object;
    }
    static function bool(mixed $value)
    {
        return is_object($value) && $value instanceof CanConvertToBoolean
            ? $value->getBooleanValue()
            : !!$value;
    }

    /**
     * Construct the target class and copy compatible declared properties, optionally selecting closure properties.
     *
     * @param Struct<object> $object
     * @param class-string<TClass> $class
     * @param array{closure?: array{skip?: bool, only?: list<string>}}|null $rule
     * @param mixed ...$constructorArgs
     * @return TClass
     * @template TClass of object
     * @throws \Throwable When target construction or property assignment fails.
     */
    public static function convert(
        Struct $object,
        string $class,
        ?array $rule = null,
        mixed ...$constructorArgs,
    ): object {
        $rule ??= [
            'closure' => [
                'skip' => true,
                'only' => [],
            ],
        ];

        $instance = new $class(...$constructorArgs);

        foreach (self::entries($object) as $key => $value) {
            if ($value instanceof \Closure) {
                $only = $rule['closure']['only'] ?? [];
                $skip = $rule['closure']['skip'] ?? true;

                if (!empty($only)) {
                    if (!in_array($key, $only, true)) {
                        continue;
                    }
                } elseif ($skip) {
                    continue;
                }
            }
            if (property_exists($instance, $key)) {
                $instance->{$key} = $value;
            }
        }

        return $instance;
    }
}
