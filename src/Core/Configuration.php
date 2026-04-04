<?php

namespace Oktaax\Core;

class Configuration
{
    private static array $storage = [];

    /**
     * Get configuration value using dot notation
     * Example: app.key, server.worker_num
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $data = self::$storage;

        foreach ($segments as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return $default;
            }
            $data = $data[$segment];
        }

        return $data;
    }

    /**
     * Set configuration value using dot notation
     */
    public static function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $data =& self::$storage;

        foreach ($segments as $segment) {
            if (!isset($data[$segment]) || !is_array($data[$segment])) {
                $data[$segment] = [];
            }
            $data =& $data[$segment];
        }

        $data = $value;
    }

    /**
     * Check if config exists
     */
    public static function has(string $key): bool
    {
        $segments = explode('.', $key);
        $data = self::$storage;

        foreach ($segments as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return false;
            }
            $data = $data[$segment];
        }

        return true;
    }

    /**
     * Get all config
     */
    public static function all(): array
    {
        return self::$storage;
    }
}