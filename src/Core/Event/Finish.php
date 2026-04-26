<?php

namespace Oktaax\Core\Event;

use Oktaax\Core\Task\Bridge;
use Oktaax\Core\Task\Completable;
use Swoole\Http\Server;
use Swoole\WebSocket\Server as WebSocketServer;

/**
 * @extends Event<array{Server|WebSocketServer,int,mixed}>
 */
class Finish extends Event
{

    public function name(): string
    {
        return "finish";
    }
    public function handle(...$args): void
    {

        [$server, $taskId, $data] = $this->unpack(...$args);
        if ($data['exception']['class']) {
            if ($data['exception']['handled']) {
                return;
            }
            $e = $data['exception'];
            throw new $e['class']($e['message']);
        }
        if (is_subclass_of($data['class'], Completable::class)) {
            \call_user_func([$data['class'], 'onComplete'], $data['result']);
            return;
        }

        Bridge::resolve($taskId, $data);
    }
}
