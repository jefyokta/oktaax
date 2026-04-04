<?php

namespace Oktaax;

/**
 * @deprecated message
 */
class ServerBag
{

    protected static $serverinstance;

    public function __construct(&$server)
    {

        static::$serverinstance = &$server;
    }
    /**
     * Get Openswoole server instance
     * @return \Swoole\Http\Server|\Swoole\WebSocket\Server
     */
    public static function &get()
    {
        return static::$serverinstance;
    }
}
