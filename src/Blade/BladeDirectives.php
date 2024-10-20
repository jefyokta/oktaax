<?php

namespace Oktaax\Blade;

class BladeDirectives
{

    public static function methodField($method)
    {
        return '<input type="hidden" name="_method" value="' . htmlspecialchars($method) . '">';
    }
    public static function csrf() {}
}
