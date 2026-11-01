<?php

namespace OktaaxExamples\Tasks;

use Oktaax\Application;
use Oktaax\Exception\TaskFailedException;
use Oktaax\Exception\TaskTimeoutException;
use Oktaax\Http\Request;
use Oktaax\Http\Response;
use Oktaax\Task\Taskable;

final class Uppercase implements Taskable
{
    public string $text = '';
    public function handle(): string
    {
        return strtoupper($this->text);
    }
}
final class SlowTask implements Taskable
{
    public function handle(): string
    {
        \Swoole\Coroutine::sleep(0.1);
        return 'finished';
    }
}
final class FailingTask implements Taskable
{
    public function handle(): mixed
    {
        throw new \DomainException('Demonstration failure');
    }
}

return function (array $config = []): Application {
    $config['server']['task_worker_num'] = 1;
    $config['server']['task_enable_coroutine'] = true;
    $app = new Application($config);
    $app->catch(
        TaskTimeoutException::class,
        fn($error, $request, Response $response) => $response
            ->status(504)
            ->json(['error' => 'Task timed out']),
    );
    $app->catch(
        TaskFailedException::class,
        fn($error, $request, Response $response) => $response
            ->status(502)
            ->json(['error' => 'Task failed']),
    );
    $app->get(
        '/',
        fn() => [
            'routes' => [
                'POST /uppercase',
                'POST /queue',
                '/timeout',
                '/failure',
            ],
        ],
    );
    $app->post('/uppercase', function (Request $request) use ($app) {
        $data = $request->validate(['text' => 'required|string']);
        return [
            'result' => $app
                ->tasks()
                ->await(Uppercase::class, $data, timeout: 2),
        ];
    });
    $app->post('/queue', function (Request $request, Response $response) use (
        $app,
    ) {
        $data = $request->validate(['text' => 'required|string']);
        $app->tasks()->dispatch(Uppercase::class, $data);
        $response->status(202)->json(['accepted' => true]);
    });
    $app->get(
        '/timeout',
        fn() => $app->tasks()->await(SlowTask::class, timeout: 0.001),
    );
    $app->get(
        '/failure',
        fn() => $app->tasks()->await(FailingTask::class, timeout: 2),
    );
    return $app;
};
