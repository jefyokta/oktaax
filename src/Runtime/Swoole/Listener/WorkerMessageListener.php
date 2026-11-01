<?php

namespace Oktaax\Runtime\Swoole\Listener;

use Oktaax\Application;
use Oktaax\Event\Event;
use Oktaax\Event\EventEmitter;
use Oktaax\Event\MultiWorkerEventEmitter;
use Oktaax\Event\WorkerMessagedEvent;
use Oktaax\Websocket\Broadcast;

final class WorkerMessageListener
{
    public function __construct(private Application $application) {}
    public function name(): string
    {
        return 'pipemessage';
    }
    public function handle(...$args): void
    {
        [$server, $source, $data] = $args;
        if ($data instanceof Broadcast) {
            $this->application->websockets()->receive($data);
            return;
        }
        if ($data instanceof Event) {
            $emitter = $this->application->container(EventEmitter::class);
            if ($emitter instanceof MultiWorkerEventEmitter) {
                $emitter->emitLocal($data->name, $data);
            } else {
                $emitter->emit($data->name, $data);
            }
            return;
        }
        $callback = $this->application->worker?->onMessage;
        if ($callback !== null) {
            $callback(new WorkerMessagedEvent($source, $data));
        }
    }
}
