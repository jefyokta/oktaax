<?php

namespace Oktaax\Trait;

use Error;
use Oktaax\Console;
use Oktaax\Http\Request as HttpRequest;
use Oktaax\Http\Response as HttpResponse;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;


trait HasWebsocket
{

    /** 
     * 
     * @var Server|null  $server
     * 
     */

    // protected $server;


    private array $websocketRoute = [];

    private $wspath = [];

    protected $events = [];





    public function ws($path, $handler)
    {
        $this->websocketRoute[$path] = $handler;
    }




    private function init()
    {
        if (!is_null($this->config['mode']) && !is_null($this->config['sock_type'])) {
            $this->server = new Server($this->host, $this->port, $this->config['mode'], $this->config['sock_type']);
        } else {
            $this->server = new Server($this->host, $this->port);
        }

        return $this;
    }

    public function onOpen($callback)
    {

        $this->events["Open"] = $callback;
    }



    public function listen($port, $host, $callback)
    {
        $this->host = $host;
        $this->port = $port;

        $path = &$this->wspath;

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
        $this->server->on("Open", function (Server $server, Request $request) use (&$path) {
            echo "Connection opened: {$request->fd}\n";
            $path[$request->fd] = $request->server['request_uri'];
            // $server->connections[$request->fd]["request_uri"] = $request->server["request_uri"];
        });
        $this->server->on("message", function (Server $server, Frame $frame) use (&$path) {
            var_dump($path);

            $server->push($frame->fd, json_encode([
                "message" => "currtime " . date('d-M-Y h:i')
            ]));
        });

        $this->server->on("close", function (Server $server, $fd) {
            unset($server->connections[$fd]);
        });

        $this->server->on("Start", $callback);
        $this->server->start();
    }
};
