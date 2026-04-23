<?php

namespace Oktaax\Trait;

use Event;
use Oktaax\Core\Application;
use Oktaax\Core\Configuration;
use Oktaax\Core\Event\Request;
use Oktaax\Core\Event\WorkerStart;
use Oktaax\Core\URL;
use Oktaax\Error\CombineException;
use Swoole\Coroutine;
use Swoole\Coroutine\Http\Server;

use function Swoole\Coroutine\run;

trait CoroutineServer
{
   

    public function listen(
        int $port,
        string|callable|null $hostOrCallback = null,
        ?callable $callback = null
    ): void {
        if (method_exists($this,'ws')) {
            throw new CombineException("cannot using websocket trait while using corotine server");
            
        }
        run(function () use ($callback, $hostOrCallback, $port) {
            $server = new Server(
                $hostOrCallback && !is_callable($hostOrCallback) ? $hostOrCallback : "127.0.0.1",
                $port,
                !!Configuration::get("server.ssl_cert_file", false)
            );
            $server->set(Configuration::get("server", []));
            $server->handle("/", [new Request, 'handle']);
            (new WorkerStart(
                new URL($server->host, $server->port, "http", false),
                is_callable($hostOrCallback) ? $hostOrCallback : $callback
            ))->handle(...[$server, Coroutine::getCid()]);
            $server->start();
        });
    }
}
