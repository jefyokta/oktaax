<?php

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Oktaax\Application\WorkerContext;
use Oktaax\Runtime\Swoole\CoroutineRuntime;
use Oktaax\Runtime\Swoole\ProcessRuntime;

$name = $argv[1];
$factory = require dirname(__DIR__, 2) . '/examples/guide/' . $name . '.php';
$app = $factory([
    'app' => [
        'host' => '127.0.0.1',
        'port' => (int) $argv[2],
        'debug' => $argv[5] === '1',
    ],
    'server' => ['worker_num' => 1, 'log_file' => $argv[3]],
]);
$ready = $argv[4];
$app->onWorkerStart(function (WorkerContext $worker) use ($ready) {
    if (!$worker->isTaskWorker()) {
        file_put_contents($ready, 'ready');
    }
});
$app->onShutdown(fn() => file_put_contents($ready . '.stopped', 'stopped'));
$app->run(
    $argv[6] === 'coroutine' ? new CoroutineRuntime() : new ProcessRuntime(),
);
