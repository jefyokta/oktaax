<?php

namespace Oktaax\Runtime\Swoole;

use Oktaax\Application;
use Oktaax\Runtime\ServerRuntime;
use Oktaax\Runtime\Swoole\Listener\HttpRequestListener;
use Swoole\Coroutine;
use Swoole\Coroutine\Http\Server;

final class CoroutineRuntime implements ServerRuntime
{
    private ?Server $server = null;
    private ?Application $application = null;
    private bool $stopping = false;
    public function __construct(private mixed $startup = null) {}

    public function validate(Application $application): void
    {
        if ($application->websockets()->hasEndpoints()) {
            throw new \LogicException(
                'CoroutineRuntime does not support WebSocket endpoints. Use ProcessRuntime.',
            );
        }
        if ($application->config('server.task_worker_num', 0) > 0) {
            throw new \LogicException(
                'CoroutineRuntime does not support task workers. Use ProcessRuntime.',
            );
        }
        if ($application->config('server.worker_num', 1) !== 1) {
            throw new \LogicException(
                'CoroutineRuntime uses one worker. Use ProcessRuntime for multiple workers.',
            );
        }
    }

    public function start(Application $application): void
    {
        $this->application = $application;
        $start = function () use ($application) {
            $config = $application->config();
            $server = new Server(
                $config->get('app.host'),
                $config->get('app.port'),
                $config->get('app.protocol') === 'https',
            );
            $this->server = $server;
            $application->attachServer($server);
            $settings = $config->get('server', []);
            unset(
                $settings['worker_num'],
                $settings['mode'],
                $settings['sock_type'],
            );
            $server->set($settings);
            $listener = new HttpRequestListener($application);
            $server->handle(
                '/',
                fn($req, $res) => $application->within(
                    fn() => $listener->handle($req, $res),
                ),
            );
            $application->workers()->start(0, false, $this->startup);
            try {
                $server->start();
            } finally {
                $application->workers()->stop();
            }
        };
        if (Coroutine::getCid() >= 0) {
            $application->within($start);
        } else {
            $failure = null;
            \Swoole\Coroutine\run(function () use (
                $application,
                $start,
                &$failure,
            ) {
                \Swoole\Process::signal(SIGTERM, fn() => $application->stop());
                \Swoole\Process::signal(SIGINT, fn() => $application->stop());
                try {
                    $application->within($start);
                } catch (\Throwable $error) {
                    $failure = $error;
                } finally {
                    \Swoole\Process::signal(SIGTERM, null);
                    \Swoole\Process::signal(SIGINT, null);
                }
            });
            if ($failure !== null) {
                throw $failure;
            }
        }
    }

    public function stop(): void
    {
        if ($this->stopping) {
            return;
        }
        $this->stopping = true;
        try {
            $this->application?->workers()->stop();
        } finally {
            $this->server?->shutdown();
        }
    }
    public function supportsTasks(): bool
    {
        return false;
    }
    public function supportsWorkerMessages(): bool
    {
        return false;
    }
    public function supportsWebSockets(): bool
    {
        return false;
    }
}
