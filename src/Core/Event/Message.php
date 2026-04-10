<?php

namespace Oktaax\Core\Event;

use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;


/**
 * @extends Event<array{0:Server,1:Frame}>
 */

class Message extends Event
{

    public function handle(...$args): void
    {
        [$server, $frame] = $this->unpack(...$args);
        throw new \Exception('Not implemented');
    }
    public function name(): string
    {
        throw new \Exception('Not implemented');
    }
}
