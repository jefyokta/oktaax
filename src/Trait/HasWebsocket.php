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
use OpenSwoole\Table;
use Oktaax\Websocket\Client;
use OpenSwoole\Http\Request;
use OpenSwoole\WebSocket\Frame;
use OpenSwoole\WebSocket\Server;
use Oktaax\Http\Request as HttpRequest;
use Oktaax\Websocket\Server as WServer;
use Oktaax\Websocket\Support\Table as SupportTable;

/**
 * Trait HasWebsocket
 *
 * A trait for implementing WebSocket functionality in Oktaax using Swoole. 
 * Provides WebSocket event handling, and server lifecycle management.
 *
 * @package Oktaax\Trait
 */
trait HasWebsocket
{
    public Table $userTable;




    private $hasChannel = false;
    private $userTableConfig = ['size' => 1024];
    /**
  
     *
     * @var array
     */
    private $actions = ["gate" => null, 'table' => null];

    /**
     * Registered WebSocket events and their handlers.
     *
     * @var array<string, callable|array>
     */
    private array $events = [];



    /**
     * Register a WebSocket event and its handler.
     *
     * @param string $event The event name to register.
     * @param callable|array $handler A callable or an array representing a class and method.
     * @return static Returns the current instance for method chaining.
     */
    public function ws(string $event, callable|array $handler)
    {
        $this->events[$event] = $handler;
        return $this;
    }

    /**
     * Initialize the WebSocket server with the given configuration.
     *
     * @return static Returns the current instance for method chaining.
     */
    private function init()
    {
        if (!is_null($this->config->mode ?? null) && !is_null($this->config->sock_type ?? null)) {
            $this->server = new Server($this->host, $this->port, $this->config->mode, $this->config->sock_type);
        } else {
            $this->server = new Server($this->host, $this->port);
        }
        $this->server->set($this->serverSettings);

        return $this;
    }

    public function gate(callable $callback)
    {

        $this->actions['gate'] = $callback;
    }

    /**
     * Handle incoming messages and dispatch them to the appropriate event handlers.
     *
     * @param Server $server The WebSocket server instance.
     * @param Frame $frame The WebSocket frame containing the client's message.
     * @return void
     * @throws Error If the event handler is invalid.
     */
    private function messageHandler(Server $server, Frame $frame): void
    {

        $request = json_decode($frame->data) ?? null;
        $client = new Client($frame->fd, $frame->data);
        $serv = new WServer($server, $client);
        // $serv->table = $this->table;

        if (!$request?->event ?? false) {
            $serv->reply('Event Needed!');
        } else {
            $this->serve($serv, $client, $request->event);
        }
    }

    /**
     * Dispatch the event to the registered handler.
     *
     * @param WServer $server The server wrapper for replying to the client.
     * @param Client $client The client wrapper for managing the connection.
     * @param string $event The event name to dispatch.
     * @return void
     * @throws Error If the handler is invalid.
     */
    private function serve(WServer $server, Client $client, string $event): void
    {
        $handler = $this->events[$event] ?? null;
        if (is_null($handler)) {
            $server->reply("Invalid event");
        } else {
            if (is_array($handler)) {
                if (!class_exists($handler[0])) {
                    throw new Error("Class [" . $handler[0] . "] Not Found");
                }
                $method = $handler[1] ?? throw new Error("Method [{$handler[1]}] not found!");
                call_user_func([$handler[0], $method], $server, $client);
            } elseif (is_callable($handler)) {
                $handler($server, $client);
            } else {
                throw new Error("Handler must be an array or callable");
            }
        }
    }



    /**
     * Start the WebSocket server and listen for incoming connections.
     *
     * @param int $port The port to listen on.
     * @param string|callable|null $hostOrcallback The host address or a callback for the server start event.
     * @param callable|null $callback A callback for the server start event if the host is not provided.
     * @return void
     */
    public function listen(int $port, string|callable|null $hostOrcallback = null, ?callable $callback = null): void
    {
        $this->host = is_string($hostOrcallback) ? $hostOrcallback : "127.0.0.1";
        $this->port = $port;
        $this->boot();
        $this->init();
        $this->makeAGlobalServer();
        $this->onRequest();
        $this->bootEvents();
        $this->eventRegistery($hostOrcallback, $callback);
        $this->server->start();
    }


    public function table(callable $callback, ?int $size = 1024)
    {
        $this->userTableConfig['size'] = $size;
        $this->actions['table'] = $callback;
    }

    /**
     * Register server events and their handlers.
     *
     * @param string|callable|null $hostOrCallback The host address or a callback for the server start event.
     * @param callable|null $callback A callback for the server start event if the host is not provided.
     * @return void
     */
    private function eventRegistery(string|callable|null $hostOrCallback = null, ?callable $callback = null): void
    {
        $this->server->on("Open", fn(Server $serv, Request $req) => $this->open($serv, $req));
        $this->server->on("Message", fn(Server $server, Frame $frame) => $this->messageHandler($server, $frame));
        $this->server->on("Close", fn(Server $server, $fd) => $this->close($server, $fd));
        $this->server->on("Start", fn() => $this->start($hostOrCallback, $callback));
    }

    /**
     * Handle the "onOpen" event.
     *
     * @param Server $server The WebSocket server instance.
     * @param Request $request The HTTP request object from the client.
     * @return void
     */
    private function open(Server $server, Request $request): void
    {

        $httpRequest = new HttpRequest($request);
        $client = new Client($request->fd);
        $serv =  new WServer($server, $client);
        if (is_callable($this->actions['gate'])) {
            $this->actions['gate']($serv, $httpRequest);
        }
    }


    private function close(Server $server, $fd)
    {

        SupportTable::remove($fd);
    }

    /**
     * Handle the "onStart" event and notify the server URL.
     *
     * @param string|callable|null $hostOrCallback The host address or a callback for the server start event.
     * @param callable|null $callback A callback for the server start event if the host is not provided.
     * @return void
     */
    private function start(string|callable|null $hostOrCallback = null, ?callable $callback = null): void
    {
        $protocol = $this->protocol === "https" ? "wss" : "ws";
        $url = "{$protocol}://{$this->host}:{$this->port}";

        if (is_callable($hostOrCallback)) {
            $hostOrCallback($url);
        } elseif (is_callable($callback) && !is_null($callback)) {
            $callback($url);
        }
    }




    private function boot()
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

    public function getHandledEvents()
    {
        return ["request", "start", "message", "open", "close"];
    }
}
