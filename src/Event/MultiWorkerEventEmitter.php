<?php

namespace Oktaax\Event;

use Oktaax\Exception\EventDispatchException;
use Override;

use function Oktaax\Utils\app;

class MultiWorkerEventEmitter extends EventEmitter
{

    #[Override]
    public function emit(string $event, array|Event $payload = []): EventEmitter
    {
        $listeners = $this->listeners[$event] ?? [];
        $onceListeners = $this->onceListeners[$event] ?? [];
        $ev = $payload instanceof Event ? $payload : new Event($event, $payload);
        $errors = [];
        unset($this->onceListeners[$event]);

        foreach ($listeners as $listener) {
            try {
                $listener($ev);
            } catch (\Throwable $th) {
                $errors[] = $th;
            }
        }

        foreach ($onceListeners as $listener) {
            try {
                $listener($ev);
            } catch (\Throwable $th) {
                $errors[] = $th;
            }
        }
        app()->worker->send($ev);
        if (!empty($errors)) {
            throw new EventDispatchException($event, $errors);
        }

        return $this;
    }
}
