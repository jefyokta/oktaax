<?php
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

    private static function writeToConsole($msg, $box, $msgColor, $output = STDOUT)
    {
       
        fwrite($output, "\n{$box} {$msgColor} {$msg}\033[0m\n");
    }
}
