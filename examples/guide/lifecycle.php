<?php

namespace OktaaxExamples\Lifecycle;

use Oktaax\Application;
use Oktaax\Application\WorkerContext;
use Oktaax\Http\Request;

final class Metrics
{
    public int $requests = 0;
    public int $ticks = 0;
    public bool $closed = false;
    public function close(): void
    {
        $this->closed = true;
    }
}

return function (array $config = []): Application {
    $app = new Application($config);
    $app->container()->singleton(
        'settings',
        (object) ['name' => 'Lifecycle demo'],
    );
    $app->container()->worker(
        Metrics::class,
        fn() => new Metrics(),
        fn(Metrics $metrics) => $metrics->close(),
    );
    $app->onWorkerStart(function (
        WorkerContext $worker,
        Application $application,
    ) {
        $metrics = $application->container(Metrics::class);
        $worker->tick(1000, function () use ($metrics) {
            $metrics->ticks++;
        });
    });
    $app->afterRequest(function () use ($app) {
        $app->container(Metrics::class)->requests++;
    });
    $app->onWorkerStop(
        fn(WorkerContext $worker) => error_log('Stopping ' . $worker->name),
    );
    $app->onShutdown(fn() => error_log('Lifecycle demo stopped'));
    $app->get('/', function (Request $request) use ($app) {
        $handle = fopen('php://temp', 'w+');
        if ($handle === false) {
            throw new \RuntimeException('Could not create a temporary stream.');
        }
        $request->defer(static fn() => fclose($handle));
        fwrite($handle, 'released at the end of this request');
        $metrics = $app->container(Metrics::class);
        return [
            'app' => $app->container('settings')->name,
            'completed_requests' => $metrics->requests,
            'ticks' => $metrics->ticks,
        ];
    });
    return $app;
};
