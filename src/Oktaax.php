<?php

namespace Oktaax;

use Oktaax\Runtime\Swoole\ProcessRuntime;
use Swoole\Http\Server;

/** Fluent convenience entry point using the process runtime. */
class Oktaax extends Application
{
    private array $customEvents = [];

    public function setServer(array|string $key, mixed $value = null): static
    {
        foreach (
            is_array($key) ? $key : [$key => $value]
            as $name => $setting
        ) {
            $this->config()->set('server.' . $name, $setting);
        }
        return $this;
    }

    public function withSSL(string $cert, string $key): static
    {
        if (!is_file($cert) || !is_file($key)) {
            throw new \InvalidArgumentException(
                'TLS certificate or key does not exist.',
            );
        }
        $this->setServer([
            'ssl_cert_file' => $cert,
            'ssl_key_file' => $key,
            'sock_type' => SWOOLE_SOCK_TCP | SWOOLE_SSL,
        ]);
        $this->config()->set('app.protocol', 'https');
        return $this;
    }

    public function useCsrf(string $appkey, int $expr = 300): static
    {
        $this->config()->set('app.key', $appkey);
        $this->config()->set('app.csrf_exp', $expr);
        $this->router()->use(
            \Oktaax\Http\Middleware\Csrf::generate($appkey, $expr),
            \Oktaax\Http\Middleware\Csrf::handle($appkey),
        );
        return $this;
    }

    public function listen(
        int $port,
        string|callable|null $hostOrCallback = null,
        ?callable $callback = null,
    ): void {
        $this->config()->set('app.port', $port);
        if (is_string($hostOrCallback)) {
            $this->config()->set('app.host', $hostOrCallback);
        }
        $startup = is_callable($hostOrCallback) ? $hostOrCallback : $callback;
        $this->run(
            new ProcessRuntime(startup: $startup, events: $this->customEvents),
        );
    }

    public function on(string $event, callable $handler): void
    {
        $this->assertConfigurable();
        $event = strtolower($event);
        if (in_array($event, $this->getHandledEvents(), true)) {
            throw new \InvalidArgumentException(
                "Event {$event} is managed by the runtime; use application lifecycle hooks.",
            );
        }
        $this->customEvents[$event] = $handler;
    }

    public function getHandledEvents(): array
    {
        return [
            'request',
            'handshake',
            'message',
            'open',
            'workerstart',
            'workerexit',
            'workerstop',
            'workererror',
            'pipemessage',
            'close',
            'task',
            'finish',
        ];
    }

    public function reload(): void
    {
        $server = $this->nativeServer();
        if (!($server instanceof Server)) {
            throw new \LogicException('Server has not started.');
        }
        $server->reload();
    }
}
