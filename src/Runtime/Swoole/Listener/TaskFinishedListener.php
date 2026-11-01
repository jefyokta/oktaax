<?php

namespace Oktaax\Runtime\Swoole\Listener;

use Oktaax\Application;
use Oktaax\Task\Completable;

final class TaskFinishedListener
{
    public function __construct(private Application $application) {}
    public function name(): string
    {
        return 'finish';
    }
    public function handle(...$args): void
    {
        [$server, $taskId, $data] = $args;
        $this->application
            ->tasks()
            ->pending()
            ->resolve((string) ($data['request_id'] ?? $taskId), $data);
        if (($data['exception']['class'] ?? null) !== null) {
            if (
                !($data['exception']['handled'] ?? false) &&
                ($data['request_id'] ?? null) === null
            ) {
                error_log(
                    'Remote task failed: ' .
                        ($data['exception']['message'] ?? 'Unknown task error'),
                );
            }
            return;
        }
        if (is_subclass_of($data['class'], Completable::class)) {
            $data['class']::onComplete($data['result']);
        }
    }
}
