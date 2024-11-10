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
use Oktaax\Http\Request as HttpRequest;
use Swoole\Table;
use Swoole\Http\Request;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;


trait HasWebsocket
{


    private $whenOpened = ["handler" => null];

    /**
     * 
     * Routes for Websocket
     * 
     * @var array 
     * 
     */


    private array $websocketRoute = [];


    /**
     * 
     * Registering Websocket route
     * 
     * @param string $path
     * @param callable|array $handler
     * 
     * @return static
     */

    public function ws(string $path, callable|array $handler)
    {
        $this->websocketRoute[$path] = $handler;

        return $this;
    }



    /**
     * 
     * Initialization server
     * @return static
     * 
     */
    private function init()
    {
        if (!is_null($this->config['mode']) && !is_null($this->config['sock_type'])) {
            $this->server = new Server($this->host, $this->port, $this->config['mode'], $this->config['sock_type']);
            $this->server->set($this->serverSettings);
        } else {
            $this->server = new Server($this->host, $this->port);
            $this->server->set($this->serverSettings);
        }

        return $this;
    }

    /**
     * 
     * Handle message event with registered routes
     * @param \Swoole\Websocket\Server $server
     * @param \Swoole\WebSocket\Frame $frame
     * @param \Swoole\Table $table
     * 
     * @return void
     * @throws Error
     */
    private function messageHandler(Server $server, Frame $frame, Table $table)
    {

        $handler = $this->websocketRoute[$table->get($frame->fd, 'request_uri')] ?? null;
        if (is_null($handler)) {
            $server->push($frame->fd, "there is no action for this endpoint");
        }

        if (is_array($handler)) {
            if (!class_exists($handler[0])) {
                throw new Error(" Class " . $handler[0] . "not Found");
            }
            $method = $handler[1] ?? throw new Error(" Method " . $handler[1] . " not found!");
            call_user_func([$handler[0], $method], $server, $frame, $table);
        } elseif (is_callable($handler)) {
            $handler($server, $frame, $table);
        } else {
            throw new Error("argument \$handler must be array or callable");
        }
    }


    public function onOpen($callback)
    {

        $this->whenOpened["handler"] = $callback;
    }

    /**
     * @param int $port
     * @param string|callback|null $hostOrcallback
     * @param callable|null $callback
     */

    public function listen($port,  $hostOrcallback = null, $callback = null)
    {
        $this->host = is_string($hostOrcallback) ? $hostOrcallback : "127.0.0.1";
        $this->port = $port;

        $table = new Table(1024);
        $table->column('fd', Table::TYPE_INT, 4);
        $table->column('details', Table::TYPE_STRING, 512);
        $table->column('request_uri', Table::TYPE_STRING, 64);
        $table->create();

        $this
            ->init()
            ->onRequest();

        if (method_exists($this, "authentication")) {
            if (is_null($this->wsKey) && property_exists($this, "wsKey")) {
                throw new Error("Cannot use authenatication with null \$wsKey. Please set key before using authentication with \$instance->setWsKey()");
            } else {
                $this->authentication();
            }
        }
        $this->server->on("Open", function (Server $server, Request $request) use (&$table) {
            echo "Connection opened: {$request->fd}\n";
            $table->set($request->fd, ["request_uri" => $request->server['request_uri'], "fd" => $request->fd]);
            $request =  new HttpRequest($request);
            if (is_callable($this->whenOpened["handler"])) {
                $this->whenOpened["handler"]($server, $request, $table);
            }
        });
        $this->server->on("message", function (Server $server, Frame $frame) use (&$table) {

            $this->messageHandler($server, $frame, $table);
        });

        $this->server->on("close", function (Server $server, $fd) use ($table) {
            $table->del($fd);
        });

        $protocol = $this->protocol === "https" ? "wss" : 'ws';

        $this->server->on("Start", function () use ($callback, $protocol, $hostOrcallback) {
            if (is_callable($hostOrcallback)) {
                $hostOrcallback($protocol . "://" . $this->host . ":" . $this->port);
            } elseif (is_callable($callback) && !is_callable($hostOrcallback)) {
                $callback($protocol . "://" . $this->host . ":" . $this->port);
            }
        });
        $this->server->start();
    }
};
