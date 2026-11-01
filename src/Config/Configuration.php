<?php

namespace Oktaax\Config;

use LogicException;

final class Configuration
{
    private bool $frozen = false;

    /**
     * Store an application-specific configuration tree.
     *
     * @param array<string, mixed> $storage
     */
    public function __construct(private array $storage = []) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->storage;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    public function set(string $key, mixed $value): void
    {
        if ($this->frozen) {
            throw new LogicException(
                'Application configuration is frozen after boot.',
            );
        }
        $target = &$this->storage;
        foreach (explode('.', $key) as $segment) {
            if (!isset($target[$segment]) || !is_array($target[$segment])) {
                $target[$segment] = [];
            }
            $target = &$target[$segment];
        }
        $target = $value;
    }

    public function has(string $key): bool
    {
        $missing = new \stdClass();
        return $this->get($key, $missing) !== $missing;
    }

    /**
     * Return the entire configuration tree without changing its frozen state.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->storage;
    }
    public function freeze(): void
    {
        $this->frozen = true;
    }
    public function isFrozen(): bool
    {
        return $this->frozen;
    }
}
