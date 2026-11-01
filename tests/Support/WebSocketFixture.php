<?php

namespace Tests\Support;

use Oktaax\Application;
use Oktaax\Websocket\Connection;
use Oktaax\Websocket\Endpoint;

final class WebSocketFixture
{
    public Application $app;
    public WebSocketTransport $transport;
    public Endpoint $endpoint;

    public function __construct()
    {
        $this->app = new Application();
        $this->endpoint = $this->app->websocket('/ws');
        $this->transport = new WebSocketTransport();
        $this->app->websockets()->attachTransport($this->transport);
    }
    public function start(): void
    {
        $this->app->boot();
        $this->app->workers()->start(0);
    }
    public function connect(
        int $fd,
        string $path = '/ws',
        array $headers = [],
    ): Connection {
        $http = new HttpFixture(uri: $path, headers: $headers);
        $http->request->fd = $fd;
        $connection = $this->app->websockets()->connect($http->request);
        $this->transport->open[$fd] = true;
        $this->app->websockets()->open($fd);
        return $connection;
    }
}
