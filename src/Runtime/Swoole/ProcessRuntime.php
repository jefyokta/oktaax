<?php

namespace Oktaax\Runtime\Swoole;

use Closure;
use Oktaax\Application;
use Oktaax\Runtime\ServerRuntime;
use Oktaax\Runtime\Swoole\Listener\HttpRequestListener;
use Oktaax\Runtime\Swoole\Listener\TaskFinishedListener;
use Oktaax\Runtime\Swoole\Listener\TaskListener;
use Oktaax\Runtime\Swoole\Listener\WorkerMessageListener;
use Oktaax\Runtime\Swoole\Listener\WebSocketHandshakeListener;
use Oktaax\Runtime\Swoole\Listener\WebSocketMessageListener;
use Swoole\WebSocket\Server as WebSocketServer;
use Swoole\Http\Server;

final class ProcessRuntime implements ServerRuntime
{
    private ?Server $server = null;

    /** @param class-string<Server> $serverClass */
    public function __construct(
        private string $serverClass = Server::class,
        private mixed $startup = null,
        private ?Closure $configureServer = null,
        private array $events = [],
    ) {}

    public function validate(Application $application): void
    {
        if (
            $application->websockets()->hasEndpoints() &&
            $application->config('server.dispatch_mode', 2) !== 2
        ) {
            throw new \InvalidArgumentException(
                'WebSocket endpoints require server.dispatch_mode=2 for stable connection ownership.',
            );
        }
        if (!is_a($this->serverClass, Server::class, true)) {
            throw new \InvalidArgumentException(
                'Invalid process server class.',
            );
        }
        if (
            $application->websockets()->hasEndpoints() &&
            $this->serverClass !== Server::class &&
            !is_a($this->serverClass, WebSocketServer::class, true)
        ) {
            throw new \InvalidArgumentException(
                'A custom server must extend Swoole\\WebSocket\\Server when WebSocket endpoints are registered.',
            );
        }
    }

    public function start(Application $application): void
    {
        $config = $application->config();
        $class =
            $this->serverClass === Server::class &&
            $application->websockets()->hasEndpoints()
                ? WebSocketServer::class
                : $this->serverClass;
        $mode = $config->get('server.mode', SWOOLE_PROCESS);
        $socket = $config->get('server.sock_type', SWOOLE_SOCK_TCP);
        $server = new $class(
            $config->get('app.host'),
            $config->get('app.port'),
            $mode,
            $socket,
        );
        $this->server = $server;
        $application->attachServer($server);
        $settings = $config->get('server', []);
        unset($settings['mode'], $settings['sock_type']);
        if ($application->websockets()->hasEndpoints()) {
            $settings['dispatch_mode'] = 2;
        }
        $server->set($settings);
        if ($application->websockets()->hasEndpoints()) {
            $application
                ->websockets()
                ->attachTransport(new WebSocketTransport($server));
            $handshake = new WebSocketHandshakeListener($application);
            $websocketMessage = new WebSocketMessageListener($application);
            $server->on(
                'handshake',
                fn($request, $response) => $handshake->handle(
                    $request,
                    $response,
                ),
            );
            $server->on(
                'message',
                fn($native, $frame) => $websocketMessage->handle(
                    $native,
                    $frame,
                ),
            );
        }
        $request = new HttpRequestListener($application);
        $message = new WorkerMessageListener($application);
        $server->on(
            'request',
            fn($req, $res) => $application->within(
                fn() => $request->handle($req, $res),
            ),
        );
        $server->on('workerstart', function ($server, $id) use ($application) {
            if (PHP_OS_FAMILY !== 'Darwin') {
                swoole_set_process_name('php oktaax.worker.' . $id);
            }
            $application->within(
                fn() => $application
                    ->workers()
                    ->start($id, $server->taskworker ?? false, $this->startup),
            );
        });
        $server->on(
            'workerexit',
            fn() => $application->within(
                fn() => $application->workers()->drain(),
            ),
        );
        $server->on(
            'workerstop',
            fn() => $application->within(
                fn() => $application->workers()->stop(),
            ),
        );
        $server->on(
            'workererror',
            fn($server, $id, $pid, $code, $signal) => $application->within(
                fn() => $application->reportWorkerError(
                    $id,
                    $pid,
                    $code,
                    $signal,
                ),
            ),
        );
        $server->on(
            'pipemessage',
            fn(...$args) => $application->within(
                fn() => $message->handle(...$args),
            ),
        );
        $server->on(
            'close',
            fn($server, $fd) => $application->within(
                fn() => $application->workers()->disconnect($fd),
            ),
        );
        if ($config->get('server.task_worker_num', 0) > 0) {
            $task = new TaskListener();
            $finish = new TaskFinishedListener($application);
            $server->on(
                'task',
                fn(...$args) => $application->within(
                    fn() => $task->handle(...$args),
                ),
            );
            $server->on(
                'finish',
                fn(...$args) => $application->within(
                    fn() => $finish->handle(...$args),
                ),
            );
        }
        foreach ($this->events as $event => $callback) {
            $server->on(
                $event,
                fn(...$args) => $application->within(
                    fn() => $callback(...$args),
                ),
            );
        }
        if ($this->configureServer !== null) {
            ($this->configureServer)($server);
        }
        $server->start();
    }

    public function stop(): void
    {
        $this->server?->shutdown();
    }
    public function supportsTasks(): bool
    {
        return true;
    }
    public function supportsWorkerMessages(): bool
    {
        return true;
    }
    public function supportsWebSockets(): bool
    {
        return true;
    }
}
