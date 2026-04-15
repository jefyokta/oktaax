<?php

namespace Oktaax\Http;

use Oktaax\Core\Application;

/**
 * Mirrors the Web API Headers interface.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/Headers
 */
class Headers implements \IteratorAggregate
{
    private  $map = [];
    private const string COOKIE_DELIMITER = "\x00";

    public function __construct(array|Headers $init = [])
    {
        $this->map = $this->makeMap();

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
    private function makeMap()
    {
        if (Application::SWOOLE_VERSION_ID >= 60100) {
            return @typed_array('<string,string>');
        }
        return [];
    }




    private function validateName(string $name): void
    {
        if ($name === '') {
            throw new \InvalidArgumentException('Header name must not be empty.');
        }

        if (!preg_match('/^[a-zA-Z0-9!#$%&\'*+\-.^_`|~]+$/', $name)) {
            throw new \InvalidArgumentException(
                "Invalid header name: '{$name}'. Only token characters are allowed."
            );
        }
    }

    private function validateValue(string $value): void
    {
        if (preg_match('/\r|\n/', $value)) {
            throw new \InvalidArgumentException(
                "Invalid header value: contains CR or LF characters (possible header injection)."
            );
        }
    }

    private function normalize(string $name): string
    {
        return strtolower($name);
    }


    /** @return string[] */
    private function decodeCookies(string $raw): array
    {
        if ($raw === '') return [];
        return explode(self::COOKIE_DELIMITER, $raw);
    }

    private function encodeCookies(array $cookies): string
    {
        return implode(self::COOKIE_DELIMITER, $cookies);
    }


    public function append(string $name, string $value): void
    {
        $this->validateName($name);
        $this->validateValue($value);

        $key   = $this->normalize($name);
        $value = trim($value);

        if ($key === 'set-cookie') {
            $cookies   = $this->decodeCookies($this->map[$key] ?? '');
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

        if (!isset($this->map[$key])) return null;

        if ($key === 'set-cookie') {
            return $this->decodeCookies($this->map[$key])[0] ?? null;
        }

        return $this->map[$key];
    }

    public function getSetCookie(): array
    {
        return $this->decodeCookies($this->map['set-cookie'] ?? '');
    }

    public function has(string $name): bool
    {
        $this->validateName($name);
        return isset($this->map[$this->normalize($name)]);
    }

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

    public function keys(): array
    {
        $keys = array_keys($this->map);
        sort($keys);
        return $keys;
    }

    public function values(): array
    {
        return array_column($this->entries(), 1);
    }


    public function forEach(callable $callback): void
    {
        foreach ($this->entries() as [$name, $value]) {
            $callback($value, $name, $this);
        }
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->entries());
    }

    public function all(): array
    {
        return $this->map;
    }
}
