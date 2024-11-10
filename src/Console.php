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
    public static function log($msg)
    {
        self::writeToConsole($msg, "\033[42m\033[30m log \033[0m", "\033[0m");
    }

    public static function error($msg)
    {
        self::writeToConsole($msg, "\033[41m\033[97m error \033[0m", "\033[31m", STDERR);
    }

    public static function warning($msg)
    {
        self::writeToConsole($msg, "\033[43m\033[30m warning \033[0m", "\033[33m");
    }

    public static function info($msg)
    {
        self::writeToConsole($msg, "\033[44m\033[30m info \033[0m", "\033[95m");
    }

    public static function custom($msg, $boxColor, $msgColor, $output = STDOUT)
    {
        self::writeToConsole($msg, $boxColor, $msgColor, $output);
    }

    public static function json(array|object $msg)
    {

        self::writeToConsole(json_encode($msg, JSON_PRETTY_PRINT), "\033[44m\033[30m info \033[0m", "\033[95m");
    }

    private static function writeToConsole($msg, $box, $msgColor, $output = STDOUT)
    {

        fwrite($output, "\n{$box} {$msgColor} {$msg}\033[0m\n");
    }
}
