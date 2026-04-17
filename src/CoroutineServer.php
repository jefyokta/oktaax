<?php

namespace Oktaax;

use Event;
use Oktaax\Core\Application;
use Oktaax\Core\Configuration;
use Oktaax\Core\Event\Request;
use Oktaax\Core\Event\WorkerStart;
use Oktaax\Core\URL;
use Oktaax\Http\Router;
use Swoole\Coroutine;
use Swoole\Coroutine\Http\Server;
use Swoole\Process\Pool;

use function Swoole\Coroutine\run;

/**
 * @mixin Router
 */

class CoroutineServer
{

    private Router $router;

    public readonly bool $taskWorker;

    private ?Pool $pool = null;

    public function __construct()
    {
        $this->router = new Router;
    }


    public function listen($port, $hostOrCallback = null, $callback = null)
    {

        run(function () use ($callback, $hostOrCallback, $port) {
            $server = new Server($hostOrCallback && !is_callable($hostOrCallback) ? $hostOrCallback : "127.0.0.1", $port);
            $server->set(Configuration::get("server", []));
            $server->handle("/", [new Request, 'handle']);
            (new WorkerStart(
                new URL($server->host, $server->port, "http", false),
                is_callable($hostOrCallback) ? $hostOrCallback : $callback
            ))->handle(...[$server, Coroutine::getCid()]);
            $server->start();
        });
    }


    public function __call($name, $arguments)
    {
        $this->router->{$name}(...$arguments);

        return $this;
    }
}
