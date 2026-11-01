<?php

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Oktaax\Application;
use Oktaax\Application\WorkerContext;
use Oktaax\Runtime\Swoole\CoroutineRuntime;
use Oktaax\Runtime\Swoole\ProcessRuntime;

$examples = [
    'http',
    'responses',
    'lifecycle',
    'database',
    'mysql',
    'sse',
    'tasks',
    'events',
    'websocket',
    'websocket-complete',
    'http-client',
    'csrf',
    'errors',
    'inertia',
];
$name = $argv[1] ?? 'http';
$runtime = getenv('OKTAAX_RUNTIME') ?: 'process';
if (
    !in_array($name, $examples, true) ||
    !in_array($runtime, ['process', 'coroutine'], true)
) {
    fwrite(
        STDERR,
        'Usage: php examples/guide/serve.php [' .
            implode('|', $examples) .
            "]\nOKTAAX_RUNTIME must be process or coroutine.\n",
    );
    exit(1);
}
if (
    $runtime === 'coroutine' &&
    in_array(
        $name,
        ['tasks', 'events', 'websocket', 'websocket-complete'],
        true,
    )
) {
    fwrite(STDERR, "The {$name} example requires OKTAAX_RUNTIME=process.\n");
    exit(1);
}
$port = filter_var(getenv('OKTAAX_PORT') ?: 3000, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 65535],
]);
if ($port === false) {
    fwrite(STDERR, "OKTAAX_PORT must be between 1 and 65535.\n");
    exit(1);
}
$config = [
    'app' => [
        'host' => getenv('OKTAAX_HOST') ?: '127.0.0.1',
        'port' => $port,
        'debug' => filter_var(
            getenv('OKTAAX_DEBUG') ?: '0',
            FILTER_VALIDATE_BOOL,
        ),
    ],
    'server' => ['worker_num' => 1],
];
$factory = require __DIR__ . '/' . $name . '.php';
$app = $factory($config);
$app->onWorkerStart(function (WorkerContext $worker, Application $app) use (
    $name,
    $runtime,
) {
    if (!$worker->isTaskWorker()) {
        echo "[{$name}/{$runtime}] {$app->address()->getHttpUrl()} ({$worker->name})\n";
    }
});
$app->run(
    $runtime === 'process' ? new ProcessRuntime() : new CoroutineRuntime(),
);
