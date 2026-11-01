<?php

namespace Oktaax\Runtime\Swoole\Listener;

use Oktaax\Task\Catchable;
use Oktaax\Task\Taskable;

final class TaskListener
{
    public function name(): string
    {
        return 'task';
    }
    public function handle(...$args): void
    {
        $server = $args[0];
        $nativeTask = $args[1] instanceof \Swoole\Server\Task ? $args[1] : null;
        $data = $nativeTask !== null ? $nativeTask->data : $args[3];
        $class = $data['class'];
        $result = [
            'class' => $class,
            'request_id' => $data['request_id'] ?? null,
            'result' => null,
            'exception' => [
                'class' => null,
                'message' => null,
                'code' => 0,
                'handled' => false,
            ],
        ];
        try {
            if (!is_subclass_of($class, Taskable::class)) {
                throw new \InvalidArgumentException('Invalid task handler.');
            }
            $handler = new $class();
            foreach ($data['payload'] ?? [] as $property => $value) {
                if (property_exists($handler, $property)) {
                    $handler->$property = $value;
                }
            }
            $result['result'] = $handler->handle();
        } catch (\Throwable $error) {
            $result['exception'] = [
                'class' => $error::class,
                'message' => $error->getMessage(),
                'code' => $error->getCode(),
                'file' => $error->getFile(),
                'line' => $error->getLine(),
                'trace' => $error->getTraceAsString(),
                'handled' => false,
            ];
            if (is_subclass_of($class, Catchable::class)) {
                try {
                    $class::catch($error);
                    $result['exception']['handled'] = true;
                } catch (\Throwable $catchError) {
                    $result['exception'] = [
                        'class' => $catchError::class,
                        'message' => $catchError->getMessage(),
                        'code' => $catchError->getCode(),
                        'handled' => false,
                    ];
                }
            }
        }
        if ($nativeTask !== null) {
            $nativeTask->finish($result);
        } else {
            $server->finish($result);
        }
    }
}
