<?php

namespace Oktaax\Blade;

use Oktaax\Middleware\Csrf;

class BladeDirectives
{
    
    public static function methodField($method)
    {
        return '<input type="hidden" name="_method" value="' . htmlspecialchars($method) . '">';
    }
    public static function csrf(){
        
    }
}
