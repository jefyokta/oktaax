<?php

/**
 * Oktaax - Real-time Websocket and HTTP Server using Swoole
 *
 * @package Oktaax
 * @author Jefyokta
 * @license MIT License
 * 
 * @link https://github.com/jefyokta/oktaax
 *
 * @copyright Copyright (c) 2024, Jefyokta
 *
 * MIT License
 *
 * Copyright (c) 2024 Jefyokta
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 */




namespace Oktaax;

class Console
{
    private static array $timers = [];
    private static int $indent = 0;



    public static function log(...$args)
    {
        self::write("log", $args, "\033[42m\033[30m", "\033[0m");
    }

    public static function error(...$args)
    {
        self::write("error", $args, "\033[41m\033[97m", "\033[31m", STDERR);
    }

    public static function warn(...$args)
    {
        self::write("warn", $args, "\033[43m\033[30m", "\033[33m");
    }

    public static function info(...$args)
    {
        self::write("info", $args, "\033[44m\033[30m", "\033[36m");
    }

    public static function debug(...$args)
    {
        self::write("debug", $args, "\033[45m\033[97m", "\033[35m");
    }

    public static function dir($data)
    {
        self::log(self::inspect($data));
    }

    public static function group(string $label = '')
    {
        self::log($label);
        self::$indent++;
    }

    public static function groupEnd()
    {
        self::$indent = max(0, self::$indent - 1);
    }

    public static function time(string $label)
    {
        self::$timers[$label] = microtime(true);
    }

    public static function timeEnd(string $label)
    {
        if (!isset(self::$timers[$label])) {
            self::warn("Timer '{$label}' not found");
            return;
        }

        $duration = (microtime(true) - self::$timers[$label]) * 1000;
        unset(self::$timers[$label]);

        self::info("%s: %d ms", $label, $duration);
    }

    public static function table(array $data)
    {
        if (empty($data)) {
            self::log("[]");
            return;
        }

        $rows = array_map(fn($r) => (array)$r, $data);
        $headers = array_keys($rows[0]);

        $widths = [];

        foreach ($headers as $h) {
            $widths[$h] = strlen($h);
        }

        foreach ($rows as $row) {
            foreach ($row as $k => $v) {
                $len = strlen(self::stringify($v));
                $widths[$k] = max($widths[$k], $len);
            }
        }

        $line = function ($row) use ($widths) {
            $out = "|";
            foreach ($row as $k => $v) {
                $v = self::stringify($v);
                $out .= " " . str_pad($v, $widths[$k]) . " |";
            }
            return $out;
        };

        $separator = "+";
        foreach ($headers as $h) {
            $separator .= str_repeat("-", $widths[$h] + 2) . "+";
        }

        fwrite(STDOUT, $separator . PHP_EOL);
        fwrite(STDOUT, $line(array_combine($headers, $headers)) . PHP_EOL);
        fwrite(STDOUT, $separator . PHP_EOL);

        foreach ($rows as $row) {
            fwrite(STDOUT, $line($row) . PHP_EOL);
        }

        fwrite(STDOUT, $separator . PHP_EOL);
    }


    private static function write(
        string $type,
        array $args,
        string $boxColor,
        string $msgColor,
        $output = STDOUT
    ) {
        $label = strtoupper($type);
        $box = "{$boxColor} {$label} \033[0m";

        $message = self::format($args);

        $indent = str_repeat("  ", self::$indent);

        fwrite($output, "\n{$indent}{$box} {$msgColor}{$message}\033[0m\n");
    }


    private static function format(array $args): string
    {
        if (empty($args)) return '';

        $first = array_shift($args);

        if (!is_string($first)) {
            return self::stringify($first) . ' ' . implode(' ', array_map([self::class, 'stringify'], $args));
        }

        $i = 0;

        $formatted = preg_replace_callback(
            '/%(\d+)?(\.\d+)?([sdjof])/',
            function ($match) use (&$args, &$i) {
                if (!isset($args[$i])) return $match[0];

                $val = $args[$i++];

                $type = $match[3]; 
                $precision = $match[2] ?? null;

                return match ($type) {
                    's' => (string)$val,
                    'd' => (string)intval($val),
                    'j' => json_encode($val),
                    'o' => self::inspect($val),
                    'f' => $precision
                        ? number_format((float)$val, (int)substr($precision, 1), '.', '')
                        : (string)(float)$val,
                    default => $match[0],
                };
            },
            $first
        );

        $rest = array_slice($args, $i);

        if (!empty($rest)) {
            $formatted .= ' ' . implode(' ', array_map([self::class, 'stringify'], $rest));
        }

        return $formatted;
    }

    private static function stringify($arg): string
    {
        if (\is_array($arg) || \is_object($arg)) {
            return self::inspect($arg);
        }

        if (\is_bool($arg)) {
            return $arg ? "true" : "false";
        }

        if ($arg === null) {
            return "null";
        }

        return (string)$arg;
    }


    private static function inspect($data, int $depth = 0): string
    {
        if ($depth > 3) return "...";

        if (is_array($data)) {
            $items = array_map(function ($k, $v) use ($depth) {
                return str_repeat("  ", $depth + 1) . "$k: " . self::inspect($v, $depth + 1);
            }, array_keys($data), $data);

            return "[\n" . implode(",\n", $items) . "\n" . str_repeat("  ", $depth) . "]";
        }

        if (\is_object($data)) {
            return self::inspect(get_object_vars($data), $depth);
        }

        return (string)$data;
    }
}
