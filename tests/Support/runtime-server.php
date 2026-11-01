<?php

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Oktaax\Application;
use Oktaax\Exception\TaskFailedException;
use Oktaax\Exception\TaskTimeoutException;
use Oktaax\Http\Response\File;
use Oktaax\Runtime\Swoole\CoroutineRuntime;
use Oktaax\Runtime\Swoole\ProcessRuntime;
use Oktaax\Task\Taskable;

final class RuntimeEchoTask implements Taskable
{
    public string $value = 'ok';
    public function handle(): mixed
    {
        return $this->value;
    }
}
final class RuntimeSlowTask implements Taskable
{
    public function handle(): mixed
    {
        Swoole\Coroutine::sleep(0.05);
        return 'late';
    }
}
final class RuntimeFailingTask implements Taskable
{
    public function handle(): mixed
    {
        throw new DomainException('remote failure');
    }
}

$mode = $argv[1];
$config = [
    'app' => ['port' => (int) $argv[2], 'name' => 'runtime-' . $mode],
    'server' => ['worker_num' => 1, 'log_file' => $argv[3]],
];
if ($mode === 'process') {
    $config['server'] += [
        'task_worker_num' => 1,
        'task_enable_coroutine' => true,
    ];
}
$app = new Application($config);
if ($mode === 'websocket') {
    $app->websocket('/socket')->on('echo', function ($connection, $message) {
        $connection->send('echo', [
            'app' => \Oktaax\Utils\app()->config('app.name'),
            'event' => $message->event(),
        ]);
    });
}
$app->router()->get(
    '/users/{id:\d+}',
    fn($req) => [
        'id' => $req->param('id'),
        'app' => \Oktaax\Utils\app()->config('app.name'),
    ],
);
$app->router()->post('/echo', fn($req) => $req->body());
$app->router()->post(
    '/validated',
    fn($req) => $req->validate(['name' => 'required|string']),
);
$app->router()->get(
    '/stream',
    fn($req, $res) => $res->stream(
        function ($write) {
            $write('first');
            $write('second');
        },
        202,
        ['content-type' => 'text/plain'],
    ),
);
$app->router()->get(
    '/file',
    fn() => new File(__FILE__, 206, ['x-file' => 'yes']),
);
$app->router()->get(
    '/mime',
    fn($req, $res) => $res->type('json')->end('{"ok":true}'),
);
$app->router()->get('/sse', function ($req, $res) {
    $sse = $res->sse();
    $sse->on('start', function () use ($sse) {
        $sse->send(['connected' => true]);
        $sse->stop();
    });
    return $sse;
});
$app->router()->get(
    '/task',
    fn() => [
        'result' => $app
            ->tasks()
            ->await(RuntimeEchoTask::class, ['value' => 'completed'], 1),
    ],
);
$app->router()->get('/task-timeout', function () use ($app) {
    try {
        $app->tasks()->await(RuntimeSlowTask::class, timeout: 0.001);
    } catch (TaskTimeoutException) {
        return [
            'timeout' => true,
            'pending' => $app->tasks()->pending()->count(),
        ];
    }
});
$app->router()->get('/task-failure', function () use ($app) {
    try {
        $app->tasks()->await(RuntimeFailingTask::class, timeout: 1);
    } catch (TaskFailedException $error) {
        return [
            'error' => $error->remoteError['class'],
            'pending' => $app->tasks()->pending()->count(),
        ];
    }
});
$app->router()->get(
    '/pending',
    fn() => ['pending' => $app->tasks()->pending()->count()],
);
$ready = $argv[4];
$startup = function (
    Application $actual,
    Swoole\Http\Server|Swoole\Coroutine\Http\Server $native,
    string $url,
) use ($app, $ready) {
    if ($actual !== $app) {
        throw new RuntimeException('Wrong application in startup callback.');
    }
    $app->container()->instance(
        'startup-result',
        (object) ['native' => $native::class, 'url' => $url],
    );
    file_put_contents($ready, 'ready');
};
$app->router()->get(
    '/startup',
    fn() => get_object_vars($app->container('startup-result')),
);
$app->onShutdown(fn() => file_put_contents($ready . '.stopped', 'stopped'));
$app->run(
    $mode === 'coroutine'
        ? new CoroutineRuntime($startup)
        : new ProcessRuntime(startup: $startup),
);
