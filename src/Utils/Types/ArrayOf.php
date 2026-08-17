<?php

namespace Oktaax\Utils\Types;

use Override;
use TypeError;

class ArrayOf extends TypeGuard
{
    /**
     * @var string[]
     */
    private array $types = [];

    private function __construct(string|TypeGuard ...$types)
    {
        $this->types = empty($types)
            ? ['mixed']
            : $types;
    }

    public static function type(string|TypeGuard ...$types): static
    {
        return new static(...$types);
    }

    public function or(string|TypeGuard $type): static
    {
        $this->types[] = $type;
        return $this;
    }

    #[Override]
    public function satisfies(string $varName, mixed $value): void
    {
        if (!\is_array($value)) {
            throw new TypeError(sprintf(
                "Property '%s' must be of type array, %s given.",
                $varName,
                get_debug_type($value)
            ));
        }

        foreach ($value as $index => $item) {
            self::assert("{$varName}[{$index}]", $item, ...$this->types);
        }
    }
}
