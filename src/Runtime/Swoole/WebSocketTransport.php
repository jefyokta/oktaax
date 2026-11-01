<?php

namespace Oktaax\Runtime\Swoole;

use Oktaax\Websocket\Transport;
use Swoole\WebSocket\Server;

final class WebSocketTransport implements Transport
{
    public function __construct(private Server $server) {}
    public function send(int $fd, string $payload, int $opcode): bool
    {
        return $this->server->push($fd, $payload, $opcode);
    }
    public function close(int $fd, int $code, string $reason): bool
    {
        return $this->server->isEstablished($fd) &&
            $this->server->disconnect($fd, $code, $reason);
    }
    public function isOpen(int $fd): bool
    {
        return $this->server->isEstablished($fd);
    }
}
