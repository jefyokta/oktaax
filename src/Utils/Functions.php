<?php

namespace Oktaax\Utils;

use Oktaax\Interfaces\Server;
use Oktaax\Interfaces\Xsocket;
use Oktaax\Oktaa;
use Oktaax\Oktaax;
use Oktaax\Trait\HasWebsocket;

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

    /**
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
