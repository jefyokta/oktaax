<?php

namespace Oktaax\Websocket\Overload;

use Oktaax\Contracts\OverloadClass;
use Oktaax\Websocket\Client;
use Oktaax\Websocket\Event;
use Oktaax\WebSocket\Server as WebSocketServer;

/**
 * @template T of WebSocketServer
 * @implements OverloadClass<T>
 */
class PushWithEvent implements OverloadClass
{

 
    protected $instance;

    public function singleton($instance)
    {
        $this->instance = $instance;
        return $this->instance;
    }

    /**
     * @param Event $event
     * @return void
     */
    public function broadcast(Event $event): void
    {
        foreach ($this->instance->fds as $fd) {
            if ($this->instance->isEstablished($fd)) {
                $this->instance->push($fd, (string)($event->client((new Client($fd)))));
            }
        }
    }
}
