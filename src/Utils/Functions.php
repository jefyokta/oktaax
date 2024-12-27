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


use Oktaax\Http\Request;
use Oktaax\Interfaces\Server;
use Oktaax\Interfaces\Xsocket;
use Oktaax\Oktaa;
use Oktaax\Oktaax;
use Oktaax\Trait\HasWebsocket;
use Illuminate\Support\Stringable;

if (! function_exists('oktaa')) {
    function oktaa()
    {
        return new Oktaa;
    }
};



if (! function_exists('oktaax')) {
    /**
     * 
     */
    function oktaax(): Server
    {


        return new Oktaax;
    }
};

if (! function_exists('xsocket')) {

    /**`
     * 
     * Get a instsance of Oktaax with websocket
     * @return Xsocket;
     */
    function xsocket(): Xsocket
    {
        return new  class extends Oktaax  implements Xsocket {
            use HasWebsocket;
        };
    }
};

/**
 * 
 * @return Request
 */
// if (! function_exists('request')) {
//     function request(): ?Request
//     {

//         return Request::getInstance() ;
//     }
// }



if (! function_exists('dd')) {
    function dd()
    {
        array_map(function ($x) {
            var_dump($x);
        }, func_get_args());
        return;
    }
}

// if (! function_exists('csrf_token') ) {

//     function csrf_token()
//     {
//         return request()->_token;
//     }
// }

if (! function_exists('stringable')) {
    function stringable(string $string): Stringable
    {
        return new Stringable($string);
    }
}