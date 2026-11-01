<?php

use Oktaax\Application;
use Oktaax\Exception\TaskFailedException;
use Oktaax\Exception\TaskTimeoutException;
use Oktaax\Runtime\Swoole\Listener\TaskFinishedListener;
use Oktaax\Runtime\Swoole\Listener\TaskListener;
use Oktaax\Task\Taskable;
use Tests\Support\Coroutines;
use Tests\Support\FakeRuntime;
use Tests\Support\TaskServer;

class LifecycleTask implements Taskable
{
    public string $value = 'ok';
    public function handle(): mixed
    {
        return $this->value;
    }
}
class FailingLifecycleTask implements Taskable
{
    public function handle(): mixed
    {
        throw new DomainException('remote failure');
    }
}

it('cleans pending tasks on timeout and ignores late completions', function () {
    Coroutines::run(function () {
        $app = new Application(['server' => ['task_worker_num' => 1]]);
        $server = new TaskServer();
        $app->run(
            new FakeRuntime(
                function ($app) use ($server) {
                    expect(
                        fn() => $app
                            ->tasks()
                            ->await(LifecycleTask::class, timeout: 0.001),
                    )->toThrow(TaskTimeoutException::class);
                    expect($app->tasks()->pending()->count())->toBe(0);
                    $late = [
                        'class' => LifecycleTask::class,
                        'request_id' => $server->messages[0]['request_id'],
                        'result' => 'late',
                        'exception' => ['class' => null],
                    ];
                    (new TaskFinishedListener($app))->handle($server, 1, $late);
                    expect($app->tasks()->pending()->count())->toBe(0);
                },
                $server,
                true,
            ),
        );
    });
});

it('cleans pending tasks when the runtime rejects dispatch', function () {
    Coroutines::run(function () {
        $app = new Application(['server' => ['task_worker_num' => 1]]);
        $server = new TaskServer();
        $server->acceptTasks = false;
        $app->run(
            new FakeRuntime(
                function ($app) {
                    expect(
                        fn() => $app
                            ->tasks()
                            ->await(LifecycleTask::class, timeout: 0.01),
                    )->toThrow(RuntimeException::class, 'Failed to dispatch');
                    expect($app->tasks()->pending()->count())->toBe(0);
                },
                $server,
                true,
            ),
        );
    });
});

it('settles results even when completion arrives during dispatch', function () {
    Coroutines::run(function () {
        $app = new Application(['server' => ['task_worker_num' => 1]]);
        $server = new TaskServer();
        $server->onTask = function ($payload) use ($app, $server) {
            (new TaskListener())->handle($server, 1, 0, $payload);
            (new TaskFinishedListener($app))->handle(
                $server,
                1,
                $server->finished,
            );
        };
        $app->run(
            new FakeRuntime(
                function ($app) {
                    expect(
                        $app
                            ->tasks()
                            ->await(
                                LifecycleTask::class,
                                ['value' => 'completed'],
                                0.01,
                            ),
                    )->toBe('completed');
                    expect($app->tasks()->pending()->count())->toBe(0);
                    try {
                        $app->tasks()->await(
                            FailingLifecycleTask::class,
                            timeout: 0.01,
                        );
                        $this->fail('Expected remote failure.');
                    } catch (TaskFailedException $error) {
                        expect($error->remoteError['class'])->toBe(
                            DomainException::class,
                        );
                        expect($error->getMessage())->toBe('remote failure');
                    }
                    expect($app->tasks()->pending()->count())->toBe(0);
                },
                $server,
                true,
            ),
        );
    });
});

it('cancels blocked task waits when the worker stops', function () {
    Coroutines::run(function () {
        $app = new Application(['server' => ['task_worker_num' => 1]]);
        $server = new TaskServer();
        $app->run(
            new FakeRuntime(
                function ($app) {
                    $done = new Swoole\Coroutine\Channel(1);
                    Swoole\Coroutine::create(function () use ($app, $done) {
                        try {
                            $app->tasks()->await(LifecycleTask::class);
                            $done->push('unexpected');
                        } catch (Throwable $error) {
                            $done->push($error);
                        }
                    });
                    expect($app->tasks()->pending()->count())->toBe(1);
                    $app->workers()->stop();
                    $error = $done->pop(2);
                    expect($error)->toBeInstanceOf(RuntimeException::class);
                    expect($error->getMessage())->toContain('cancelled');
                    expect($app->tasks()->pending()->count())->toBe(0);
                },
                $server,
                true,
            ),
        );
    });
});
