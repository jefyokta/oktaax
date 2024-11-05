<?php

namespace Oktaax\Interfaces;

use Swoole\Http\Server as HttpServer;
use Swoole\WebSocket\Server as WebSocketServer;

interface Server
{
    public function listen(int $port, string $host, callable $callback);
}
