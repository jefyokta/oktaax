<?php

namespace Oktaax\Websocket;

use Oktaax\Interfaces\Channel;
use Oktaax\Websocket\Support\Member;
use Swoole\WebSocket\Frame;

class Client
{
    /**
     * Client's file descriptor (fd).
     */
    public int $fd;


    /**
     * Client's Data (could be string or decoded JSON).
     * @var mixed
     */
    public mixed $data = null;

    /**
     * Client's Identification Information.
     * @var array
     */
    private array $identify = [];

    /**
     * Constructor to initialize client from a WebSocket frame.
     */
    public function __construct(int $fd, $data = null)
    {
        $this->fd = $fd;

        if (! is_null($data)) {
            $decodedData =  json_decode($data, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->data = $decodedData;
            } else {
                $this->data = $data;
            }
        }
    }

    /**
     * Check if the client is in a specific channel.
     * 
     * @param Channel|string $channel
     * @return bool
     */
    public function inChannel(Channel $channel): bool
    {
        return (new $channel)->eligible($this);
    }
}
