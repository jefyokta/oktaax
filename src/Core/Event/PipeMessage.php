<?php

namespace Oktaax\Core\Event;

use Oktaax\Event\EventEmitter;
use Oktaax\Event\WorkerMessagedEvent;
use Override;
use Oktaax\Event\Event as Ev;

use function Oktaax\Utils\app;

/**
 * @extends parent<array{\Swoole\Http\Server,int,mixed}>
 */
class PipeMessage extends Event
{
    #[Override]
    public function name(): string
    {
        return "pipemessage";
    }
    #[Override]
    public function handle(...$args): void
    {
        [$server, $src, $data] = $this->unpack(...$args);
        if ($data instanceof Ev) {
            app()->container(EventEmitter::class)->emit($data->name, ...$data->data);
            return;
        }

        if (app()->worker->onMessage !== null) {            
            (app()->worker->onMessage)(new WorkerMessagedEvent($src, $data));
            return;
        }
    }
}
