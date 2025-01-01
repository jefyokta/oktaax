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

use Error;
use Oktaax\Error\EventNotDecleared;
use Oktaax\Interfaces\Channel;
use Oktaax\Interfaces\WebSocketServer;
use OpenSwoole\Coroutine;
use OpenSwoole\WebSocket\Server as SWServer;

class Server implements WebSocketServer
{

    public int|array $fds = [];

    protected Client $client;
    public $event;
    public static $eventDefault = 'general';

    private $messages = [
        "event"=>null,
        "message" => null
    ];

    public SWServer $swooleWebsocket;

    public function __construct(SWServer $server, Client $client)
    {
        $this->client = $client;
        $this->swooleWebsocket = $server;
        $this->messages["event"] = static::$eventDefault ?? null;
    }


    private function push($fd, $data, $opcode = 1, $flags = 1)
    {
        if (is_null($this->event) && null === static::$eventDefault) {
            throw new EventNotDecleared("Cannot push a message without event");
        }
        if (is_array($data) || is_object($data)) {
            $data = json_encode([
                "event" => $this->event ?? static::$eventDefault,
                "message" => $data
            ], JSON_PRETTY_PRINT);
        }
        $this->swooleWebsocket->push($fd, $data, $opcode, $flags);
    }



    public function broadcast(mixed $data, int $delay = 0, $opcode = 1, $flags = 1): void
    {
        $receivers = $this->fds ?? $this->swooleWebsocket->connections;

        if (is_int($receivers)) {
            $message = is_callable($data) ? $data(new Client($receivers)) : $data;
            $this->push($receivers, $message, $opcode, $flags);
        } else {

            if (is_array($receivers)) {
                foreach ($receivers as $fd) {
                    $message = is_callable($data) ? $data(new Client($fd)) : $data;
                    $this->push($fd, $message, $opcode, $flags);

                    if ($delay > 0) {
                        Coroutine::sleep($delay);
                    }
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
        if (! new $channel instanceof Channel) {
            throw new Error("$channel must implements Oktaax\\Interfaces\\Channel");
        }

        foreach ($this->swooleWebsocket->connections as $c) {
            if ((new $channel)->eligible(new Client($c))) {
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

    public function kickSender($reason = 'Kicked by server', $code = SWServer::WEBSOCKET_CLOSE_NORMAL)
    {
        $this->swooleWebsocket->disconnect($this->getSenderFd(), $code, $reason);
    }

    public function reject($fd, $reason, $code = \Swoole\WebSocket\Server::WEBSOCKET_CLOSE_NORMAL)
    {
        $this->swooleWebsocket->disconnect($fd, $code, $reason);
    }
    public function event($eventName)
    {

        $this->event = $eventName;
        return $this;
    }

    public static function setDefaultEvent($eventName)
    {

        static::$eventDefault = $eventName;
    }
};
