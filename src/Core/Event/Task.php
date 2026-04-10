<?php

namespace Oktaax\Core\Event;

use Oktaax\Console;
use Oktaax\Core\Application;
use Oktaax\Core\Task\Catchable;
use Throwable;
use Swoole\Http\Server as HttpServer;
use Swoole\Server\Task as ServerTask;
use Swoole\WebSocket\Server;
use Oktaax\Core\Task\Taskable;


/**
 * @extends Event<array{Server|HttpServer,ServerTask}>
 */
class Task extends Event
{

    private $coroutineEnable = false;
    public function handle(...$args): void
    {
        [$server, $task] = $this->unpack(...$args);
        $data = $this->getPayload($args);
        $class = $data["class"];

        $payload = $data['payload'] ?? [];
        $result = [
            'result'    => null,
            'exception' => [
                'class'   => null,
                'message' => null,
                'code'    => null,
                'file'    => null,
                'line'    => null,
                'trace'   => null,
            ],
            "class" => $class
        ];


        try {
            /** @var class-string<Taskable> */
            if (!is_subclass_of($class, Taskable::class)) {
                throw new \RuntimeException(
                    \sprintf('Task [%s] must implement %s', $task, Taskable::class)
                );
            }

            $handler = new $class;

            foreach ($payload as $property => $value) {
                if (property_exists($handler, $property)) {
                    $handler->{$property} = $value;
                }
            }

            $result['result'] = $handler->handle();
        } catch (Throwable $e) {
            if (is_subclass_of($class, Catchable::class)) {
                \call_user_func([$class, 'catch'], $e);
                return;
            }
            $result['exception'] = [
                'class'   => \get_class($e),
                'message' => $e->getMessage(),
                'code'    => $e->getCode(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
            ];
        } finally {
            if ($this->coroutineEnable) {
                $task->finish($result);
                return;
            }
            $server->finish($result);
        }
    }

    public function name(): string
    {
        return 'task';
    }


    /**
     * task worker with enabled coroutine, task event will have different params
     * @param mixed $args
     */
    private function getPayload($args)
    {
        if ($args[1] instanceof ServerTask) {
            $this->coroutineEnable = true;
            return $args[1]->data;
        }

        return $args[3];
    }
}
