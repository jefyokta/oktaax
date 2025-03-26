<?php

namespace Oktaax;


class ServerBag
{

    protected static $serverinstance;

    public function __construct(&$server)
    {

        static::$serverinstance = &$server;
    }
    /**
     * Get Openswoole server instance
     * @return \OpenSwoole\Http\Server|\OpenSwoole\WebSocket\Server
     */
    public static function &get()
    {
        return static::$serverinstance;
    }
}
