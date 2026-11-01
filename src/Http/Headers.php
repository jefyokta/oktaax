<?php

namespace Oktaax\Http;

/**
 * Normalize and validate header names/values while preserving separate Set-Cookie values.
 *
 * @implements \IteratorAggregate<int, array{string, string}>
 * @see https://developer.mozilla.org/en-US/docs/Web/API/Headers
 */
class Headers implements \IteratorAggregate
{
    /**
     * Normalized header strings; Set-Cookie values are joined internally with a NUL delimiter.
     *
     * @var array<string, string>
     */
    private $map = [];
    private const string COOKIE_DELIMITER = "\x00";

    /**
     * Copy headers or normalize a header map; trusted=true skips token/value validation.
     *
     * @param array<string, string|list<string>>|self $init
     * @param bool $trusted
     */
    public function __construct(
        array|Headers $init = [],
        private $trusted = false,
    ) {
        if ($init instanceof Headers) {
            foreach ($init->entries() as [$key, $value]) {
                $this->append($key, $value);
            }
            return;
        }

        foreach ($init as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $v) {
                    $this->append($key, $v);
                }
            } else {
                $this->append($key, $value);
            }
        }
    }

    private function validateName(string $name): void
    {
        if ($name === '') {
            throw new \InvalidArgumentException(
                'Header name must not be empty.',
            );
        }
        if ($this->trusted) {
            return;
        }

        if (!preg_match('/^[a-zA-Z0-9!#$%&\'*+\-.^_`|~]+$/', $name)) {
            throw new \InvalidArgumentException(
                "Invalid header name: '{$name}'. Only token characters are allowed.",
            );
        }
    }

    private function validateValue(string $value): void
    {
        if ($this->trusted) {
            return;
        }
        if (preg_match('/\r|\n/', $value)) {
            throw new \InvalidArgumentException(
                'Invalid header value: contains CR or LF characters (possible header injection).',
            );
        }
    }

    private function normalize(string $name): string
    {
        return strtolower($name);
    }

    /**
     * Split the internal cookie delimiter into separate response header values.
     *
     * @return list<string>
     */
    private function decodeCookies(string $raw): array
    {
        if ($raw === '') {
            return [];
        }
        return explode(self::COOKIE_DELIMITER, $raw);
    }

    /**
     * Join Set-Cookie values without conflating commas inside cookie attributes.
     *
     * @param list<string> $cookies
     */
    private function encodeCookies(array $cookies): string
    {
        return implode(self::COOKIE_DELIMITER, $cookies);
    }

    public function append(string $name, string $value): void
    {
        $this->validateName($name);
        $this->validateValue($value);

        $key = $this->normalize($name);
        $value = trim($value);

        if ($key === 'set-cookie') {
            $cookies = $this->decodeCookies($this->map[$key] ?? '');
            $cookies[] = $value;
            $this->map[$key] = $this->encodeCookies($cookies);
            return;
        }

        if (isset($this->map[$key])) {
            $this->map[$key] .= ', ' . $value;
        } else {
            $this->map[$key] = $value;
        }
    }

    public function delete(string $name): void
    {
        $this->validateName($name);
        unset($this->map[$this->normalize($name)]);
    }

    public function get(string $name): ?string
    {
        $this->validateName($name);
        $key = $this->normalize($name);

        if (!isset($this->map[$key])) {
            return null;
        }

        if ($key === 'set-cookie') {
            return $this->decodeCookies($this->map[$key])[0] ?? null;
        }

        return $this->map[$key];
    }

    /**
     * Return separate Set-Cookie field values without comma splitting.
     *
     * @return list<string>
     */
    public function getSetCookie(): array
    {
        return $this->decodeCookies($this->map['set-cookie'] ?? '');
    }

    public function has(string $name): bool
    {
        $this->validateName($name);
        return isset($this->map[$this->normalize($name)]);
    }

    /**
     * Replace one header or all Set-Cookie values after validation.
     *
     * @param string|list<string> $value
     */
    public function set(string $name, string|array $value): void
    {
        $this->validateName($name);
        $this->validateValue($value);

        $key = $this->normalize($name);

        if ($key === 'set-cookie') {
            $this->map[$key] = $this->encodeCookies([trim($value)]);
            return;
        }

        $this->map[$key] = trim($value);
    }

    /**
     * Return sorted name/value pairs, preserving repeated Set-Cookie entries.
     *
     * @return list<array{string, string}>
     */
    public function entries(): array
    {
        $result = [];
        $sorted = $this->map;
        ksort($sorted);

        foreach ($sorted as $key => $raw) {
            if ($key === 'set-cookie') {
                foreach ($this->decodeCookies($raw) as $cookie) {
                    $result[] = [$key, $cookie];
                }
            } else {
                $result[] = [$key, $raw];
            }
        }

        return $result;
    }

    /**
     * Return sorted unique lowercase header names.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        $keys = array_keys($this->map);
        sort($keys);
        return $keys;
    }

    /**
     * Return header values in entry order, including individual Set-Cookie fields.
     *
     * @return list<string>
     */
    public function values(): array
    {
        return array_column($this->entries(), 1);
    }

    /**
     * Invoke the callback with value, normalized name, and this collection for each entry.
     *
     * @param callable(string, string, self): mixed $callback
     */
    public function forEach(callable $callback): void
    {
        foreach ($this->entries() as [$name, $value]) {
            $callback($value, $name, $this);
        }
    }

    /**
     * Iterate sorted name/value pairs rather than an associative name map.
     *
     * @return \ArrayIterator<int, array{string, string}>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->entries());
    }

    /**
     * Return the internal normalized string map; Set-Cookie strings use the internal delimiter.
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->map;
    }
}
