<?php

namespace Oktaax\Websocket;

use Error;
use Oktaax\Interfaces\Channel;
use Oktaax\Interfaces\WebSocketServer;
use OpenSwoole\Coroutine;
use OpenSwoole\Table;
use OpenSwoole\WebSocket\Server as SWServer;

class Server implements WebSocketServer
{

    public int|array $fds = [];

    public Client $client;

    public Table $table;

    public SWServer $swooleWebsocket;

    private function push($fd, $data, $opcode = 1, $flags = 1)
    {
        $this->swooleWebsocket->push($fd, $data, $opcode, $flags);
    }
    public function __construct(SWServer $server, Client $client)
    {
        $this->client = $client;
        $this->swooleWebsocket = $server;
    }


    public function broadcast(mixed $data, int $delay = 0, $opcode = 1, $flags = 1): void
    {
        $receivers = $this->fds ?? $this->swooleWebsocket->connections;

        if (is_int($receivers)) {
            $message = is_callable($data) ? $data(new Client($receivers)) : $data;
            $this->swooleWebsocket->push($receivers, $message, $opcode, $flags);
        } else {

            if (is_array($receivers)) {
                foreach ($receivers as $fd) {
                    $message = is_callable($data) ? $data(new Client($fd)) : $data;
                    $this->swooleWebsocket->push($fd, $message, $opcode, $flags);

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

    public function reject($fd, $reason, $code)
    {
        $this->swooleWebsocket->disconnect($fd, \Swoole\WebSocket\Server::WEBSOCKET_CLOSE_NORMAL, $reason);
    }
};
