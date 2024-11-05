<?php

namespace Oktaax\Blade;

use Oktaax\Http\Request;

class BladeDirectives
{

    public static function methodField($method)
    {
        return '<input type="hidden" name="_method" value="' . htmlspecialchars($method) . '">';
    }
    public static function csrf(Request $request)
    {

        $token =  $request->_token;

        return '<input type="hidden" name="_token" value="' . $token . '">';
    }
}
