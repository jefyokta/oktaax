<?php

namespace Oktaax\Runtime\Swoole\Listener;

use Oktaax\Application;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;

final class WebSocketMessageListener
{
    public function __construct(private Application $application) {}
    public function handle(Server $server, Frame $frame): void
    {
        $this->application
            ->websockets()
            ->message($frame->fd, $frame->data, $frame->opcode);
    }
}
