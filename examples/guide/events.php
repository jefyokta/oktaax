<?php

use Oktaax\Application;
use Oktaax\Application\WorkerContext;
use Oktaax\Event\EventEmitter;
use Oktaax\Http\Request;

return function (array $config = []): Application {
    $config['server']['worker_num'] = 2;
    $app = new Application($config);
    $app->container()->worker('counter', fn() => (object) ['value' => 0]);
    $app->onWorkerStart(function (
        WorkerContext $worker,
        Application $application,
    ) {
        $counter = $application->container('counter');
        $application
            ->container(EventEmitter::class)
            ->on('increment', function () use ($counter) {
                $counter->value++;
            });
    });
    $app->get(
        '/',
        fn() => [
            'worker' => $app->worker->name,
            'counter' => $app->container('counter')->value,
        ],
    );
    $app->post('/increment', function (Request $request) use ($app) {
        $app->container(EventEmitter::class)->emit('increment');
        return ['published' => true, 'worker' => $app->worker->name];
    });
    return $app;
};
