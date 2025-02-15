<?php

namespace Oktaax;


class ServerBag
{

    protected static $serverinsance;

    public function __construct(&$server)
    {

        static::$serverinsance = &$server;
    }
    /**
     * Get Openswoole server instance
     * @return \OpenSwoole\Http\Server|\OpenSwoole\WebSocket\Server
     */
    public static function &get()
    {
        return static::$serverinsance;
    }
}
