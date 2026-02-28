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

use Oktaax\Contracts\OverloadClass;
use Oktaax\Interfaces\Channel;
use Oktaax\Interfaces\WebSocketServer;
use Oktaax\Trait\Overloadable;
use Oktaax\Websocket\Overload\PushWithEvent;
use Oktaax\Websocket\Support\Table;
use Stringable;
use Swoole\Coroutine;
use Swoole\Table as SwooleTable;
use Swoole\WebSocket\Server as SWServer;
use TypeError;

/**
 * @method mixed broadcast(callable(\Oktaax\Websocket\Client) $callback = null, int $delay = 0, $opcode = 1, $flags = 1)
 * @method mixed broadcast(\Oktaax\Websocket\Event | string $message = null, int $delay = 0, $opcode = 1, $flags = 1)
 */

class Server implements WebSocketServer 
{
    use Overloadable;

    public int|array $fds = [];

    protected Client $client;


    public SWServer $swooleWebsocket;

    public function __construct(SWServer $server, Client $client)
    {
        $this->client = $client;
        $this->swooleWebsocket = $server;
        self::classRegister(PushWithEvent::class);
    }



    private function push($fd, $data, $opcode = 1, $flags = 1)
    {
        $data = is_scalar($data) ? $data : json_encode($data);
        if ($this->swooleWebsocket->isEstablished($fd)) {
            $this->swooleWebsocket->push($fd, $data, $opcode, $flags);
        }
    }

    private function normalizeMessage(Event|string|array $message, Client $client): string
    {
        if ($message instanceof Event) {
            return $message->client($client);
        }

        if (is_string($message) && class_exists($message) && is_subclass_of($message, Event::class)) {
            return (new $message)->client($client);
        }

        if (is_string($message)) {
            return $message;
        }

        return json_encode($message);
    }

    public function broadcast(mixed $data, int $delay = 0, int $opcode = 1, int $flags = 1): void
    {   
        $receivers = $this->fds ?? Table::getTable();

        $send = function (int $fd) use ($data, $opcode, $flags) {

            $client = new Client($fd);

            $payload = is_callable($data)
                ? $data($client)
                : $data;

            $message = $this->normalizeMessage($payload, $client);

            $this->push($fd, $message, $opcode, $flags);
        };

        if (is_int($receivers)) {
            $send($receivers);
            return;
        }

        if (is_array($receivers) || $receivers instanceof SwooleTable) {
            foreach ($receivers as $fd) {
                $send($fd);

                if ($delay > 0) {
                    Coroutine::sleep($delay);
                }
            }
        }
    }



    public function to(int|array $fds): static
    {
        $this->fds = $fds;
        return $this;
    }

    public function toChannel($channel): static
    {
        if (!class_exists((string)$channel)) {
            throw new TypeError("Class {$channel} does not exist.");
        }

        if (!is_subclass_of($channel, Channel::class)) {
            throw new TypeError(
                "Param must be subclass of " . Channel::class . ", {$channel} given"
            );
        }
        $channel = new Channel;

        foreach (Table::getTable() as $c) {
            if ($channel->eligible(new Client($c))) {
                $this->fds[] = $c;
            }
        }
        return $this;
    }


    public function reply($data)
    {
        $this->push($this->client->fd, $data);
    }


    public function tick($ms, $callback, ...$params)
    {

        $this->swooleWebsocket->tick($ms, $callback, ...$params);
    }

    public function after($ms, $callback, ...$params)
    {
        $this->swooleWebsocket->after($ms, $callback, ...$params);
    }


    public function getSender(): Client
    {
        return $this->client;
    }

    public function getSenderFd(): int
    {
        return $this->client->fd;
    }

    public function getSenderData(): mixed
    {
        return $this->client->data;
    }

    public function kickSender($reason = 'Kicked by server', $code = WEBSOCKET_CLOSE_NORMAL)
    {
        $this->swooleWebsocket->disconnect($this->getSenderFd(), $code, $reason);
    }

    public function reject($fd, $reason, $code = WEBSOCKET_CLOSE_NORMAL)
    {
        $this->swooleWebsocket->disconnect($fd, $code, $reason);
    }
};
