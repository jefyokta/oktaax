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

    public static function warning(...$args){
        return self::warn(...$args);
    }

    public static function info(...$args)
    {
        self::write("info", $args, "\033[44m\033[30m", "\033[36m");
    }

    public static function debug(...$args)
    {
        self::write("debug", $args, "\033[45m\033[97m", "\033[35m");
    }

    public static function table(array $data)
    {
        if (empty($data)) {
            self::log("[]");
            return;
        }

        $headers = array_keys((array)$data[0]);

        $output = implode("\t", $headers) . PHP_EOL;

        foreach ($data as $row) {
            $output .= implode("\t", (array)$row) . PHP_EOL;
        }

        self::write("table", [$output], "\033[46m\033[30m", "\033[0m");
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

        self::info("{$label}: " . number_format($duration, 2) . " ms");
    }

    public static function dump(...$args)
    {
        foreach ($args as $arg) {
            fwrite(STDOUT, "\n");
            var_dump($arg);
        }
    }

    private static function stringify($arg): string
    {
        if (is_array($arg) || is_object($arg)) {
            return json_encode($arg, JSON_PRETTY_PRINT);
        }

        if (is_bool($arg)) {
            return $arg ? "true" : "false";
        }

        if ($arg === null) {
            return "null";
        }

        return (string)$arg;
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

        $message = array_map(fn($a) => self::stringify($a), $args);
        $message = implode(" ", $message);

        fwrite($output, "\n{$box} {$msgColor}{$message}\033[0m\n");
    }
}
