<?php

namespace Oktaax\Event;

use Closure;
use Oktaax\Application\WorkerContext;
use Oktaax\Exception\EventDispatchException;

class MultiWorkerEventEmitter extends EventEmitter
{
    /**
     * Bind this emitter to a worker and an optional application-scope listener invoker.
     *
     * @param (\Closure(\Closure(): mixed): mixed)|null $invoke
     */
    public function __construct(
        private WorkerContext $worker,
        ?Closure $invoke = null,
    ) {
        parent::__construct($invoke);
    }

    /**
     * Deliver an event locally and forward it to other HTTP workers without waiting for peer delivery.
     *
     * @param array<array-key, mixed>|\Oktaax\Event\Event<mixed> $payload
     * @throws \Oktaax\Exception\EventDispatchException When a local listener fails.
     */
    public function emit(string $event, array|Event $payload = []): EventEmitter
    {
        $value =
            $payload instanceof Event ? $payload : new Event($event, $payload);
        $value->sender = [
            'name' => $this->worker->name,
            'id' => $this->worker->id,
        ];
        $failure = null;
        try {
            parent::emit($event, $value);
        } catch (EventDispatchException $error) {
            $failure = $error;
        }
        $this->worker->send($value);
        if ($failure !== null) {
            throw $failure;
        }
        return $this;
    }

    /**
     * Deliver an event only to local listeners, without forwarding or rebroadcast.
     *
     * @param array<array-key, mixed>|\Oktaax\Event\Event<mixed> $payload
     * @throws \Oktaax\Exception\EventDispatchException When a local listener fails.
     */
    public function emitLocal(
        string $event,
        array|Event $payload = [],
    ): EventEmitter {
        return parent::emit($event, $payload);
    }
}
