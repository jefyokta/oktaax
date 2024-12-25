<?php

/**
 * Oktaax - Real-time Websocket and HTTP Server using Swoole
 *
 * @package Oktaax
 * @author Jefyokta
 * @license MIT License
 * 
 * @link https://github.com/jefyokta/oktaax
 *
 * @copyright Copyright (c) 2024, Jefyokta
 *
 * MIT License
 *
 * Copyright (c) 2024 Jefyokta
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 */



namespace Oktaax\Websocket;

use Oktaax\Interfaces\Channel;
use Oktaax\Websocket\Support\Member;

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
            $this->data = json_last_error() === JSON_ERROR_NONE ? $decodedData : $data;
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
