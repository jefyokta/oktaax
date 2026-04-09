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



namespace Oktaax\Trait;

use Error;
use Oktaax\Console;
use Swoole\Table;
use Oktaax\Websocket\Client;
use Swoole\Http\Request;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;
use Oktaax\Http\Request as HttpRequest;
use Oktaax\Websocket\Event;
use Oktaax\Websocket\Server as WServer;
use Oktaax\Websocket\Support\Table as SupportTable;

trait HasWebsocket
{
    public Table $userTable;
    private $userTableConfig = ['size' => 1024];

    private $actions = [
        "gate" => null,
        "table" => null,
        "withOutEvent" => null,
        "exit" => null
    ];

    private array $events = [];

    protected $startParams = ["hostOrCallback" => null, "callback" => null];

    protected function getServerClass(): string
    {
        return Server::class;
    }

    public function ws(string $event, ?callable $handler = null)
    {
        if (class_exists($event) && is_subclass_of($event, Event::class)) {
            $this->events[$event::name()] = new $event;
            return $this;
        }

        $this->events[$event] = $handler;
        return $this;
    }

    public function gate(callable $callback)
    {
        $this->actions['gate'] = $callback;
    }

    public function exit(callable $callback)
    {
        $this->actions['exit'] = $callback;
    }

    public function withOutEvent($callback)
    {
        $this->actions["withOutEvent"] = $callback;
    }

    public function table(callable $callback, ?int $size = 1024)
    {
        $this->userTableConfig['size'] = $size;
        $this->actions['table'] = $callback;
    }


    private function messageHandler(Server $server, Frame $frame): void
    {
        $packet = $this->parseIncoming($frame->data);
        $client = new Client($frame->fd, $packet);
        $serv   = new WServer($server, $client);

        if (!$packet['event']) {
            if (is_callable($this->actions["withOutEvent"])) {
                $this->actions["withOutEvent"]($serv, $client);
            } else {
                $serv->reply('Event Needed!');
            }
            return;
        }

        $this->serve($serv, $client, $packet['event']);
    }


    private function parseIncoming(string $data): array
    {
        if ($this->isBinary($data)) {
            Console::log("binary");
            return $this->parseBinary($data);
        }
        // Console::log("json");

        return $this->parseJson($data);
    }

    /**
     *  Detect Binary Protocol
     */
    private function isBinary(string $data): bool
    {
        return isset($data[0]) && ord($data[0]) === 0x01;
    }

    /**
     * JSON Parser
     */
    private function parseJson(string $data): array
    {
        $decoded = json_decode($data, true);

        return [
            'event' => $decoded['event'] ?? null,
            'data'  => $decoded,
            'meta'  => [],
            'type'  => 'json'
        ];
    }

    /**
     *  Binary Protocol Parser
     *
     * Format:
     * | 1B | 1B | 2B | 1B | N | payload |
     * |VER |TYPE|FLAG|ELEN|EVT| DATA    |
     */
    private function parseBinary(string $data): array
    {
        $ver   = ord($data[0]);
        $type  = ord($data[1]);
        $flags = unpack('n', substr($data, 2, 2))[1];

        $eventLength = ord($data[4]);
        $event = substr($data, 5, $eventLength);

        $offset = 5 + $eventLength;
        $payload = substr($data, $offset);

        return [
            'ver'   => $ver,
            'type'  => $type,
            'flags' => $flags,
            'event' => $event,
            'data'  => $payload,
            'meta'  => [],
        ];
    }

    /**
     *  Dispatch Event
     */
    private function serve(WServer $server, Client $client, ?string $event): void
    {
        $handler = $this->events[$event] ?? null;

        if ($handler instanceof Event) {
            $handler->client = $client;
            $handler->server = $server;
            $handler->broadcast();
            return;
        }

        if ($handler == null) {
            $server->reply("Invalid event");
            return;
        }

        if (is_array($handler)) {
            if (!class_exists($handler[0])) {
                throw new Error("Class [" . $handler[0] . "] Not Found");
            }

            $method = $handler[1] ?? throw new Error("Method not found!");
            call_user_func([$handler[0], $method], $server, $client);

        } elseif (is_callable($handler)) {
            $handler($server, $client);

        } else {
            throw new Error("Handler must be callable or array");
        }
    }

    /**
     *  Broadcast
     */
    public function broadcast($data, $receivers = null)
    {
        foreach (SupportTable::getTable() as $key => $value) {
            $this->server->push(
                $key,
                is_string($data) ? $data : json_encode($data)
            );
        }
    }

    /**
     * ⚙️ Event Registry
     */
    protected function eventRegistery(): void
    {
        $this->server->on("Open", fn(Server $serv, Request $req) => $this->open($serv, $req));
        $this->server->on("Message", fn(Server $server, Frame $frame) => $this->messageHandler($server, $frame));
        $this->server->on("Close", fn(Server $server, $fd) => $this->close($server, $fd));
    }

    private function open(Server $server, Request $request): void
    {
        $httpRequest = new HttpRequest($request);
        $client = new Client($request->fd);
        $serv = new WServer($server, $client);

        $this->callIfCallable($this->actions['gate'], $serv, $httpRequest);
    }

    private function close(Server $server, $fd)
    {
        $s = new WServer($server, new Client($fd));
        $this->callIfCallable($this->actions["exit"], $s, $fd);
    }

    protected function boot()
    {
        $this->userTable = new Table($this->userTableConfig['size']);
        $this->callIfCallable($this->actions['table'], $this->userTable);
        SupportTable::boot($this->userTable);
    }

    private function callIfCallable(?callable $callback, &...$params)
    {
        if (is_callable($callback)) {
            try {
                $callback(...$params);
            } catch (\Throwable $th) {
                throw new \RuntimeException($th->getMessage(), 0, $th);
            }
        }
    }

    public function getHandledEvents(): array
    {
        return ["request", "start", "message", "open", "close"];
    }

    public static function buildPacket(
        string $event,
        string $payload = '',
        int $type = 1,
        int $flags = 0
    ): string {
        return
            chr(1) .
            chr($type) .
            pack('n', $flags) .
            chr(strlen($event)) .
            $event .
            $payload;
    }
}