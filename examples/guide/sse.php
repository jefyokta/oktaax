<?php

use Oktaax\Application;
use Oktaax\Event\Event;
use Oktaax\Event\EventEmitter;
use Oktaax\Http\Request;
use Oktaax\Http\Response;
use function Oktaax\Utils\clearInterval;

return function (array $config = []): Application {
    $app = new Application($config);
    $app->get(
        '/',
        fn() => 'Use curl -N /events for three ticks, or /subscribe with POST /publish.',
    );
    $app->get('/events', function (Request $request, Response $response) use (
        $app,
    ) {
        $sse = $response->sse();
        $timer = null;
        $request->connection()->defer(function () use (&$timer) {
            if ($timer !== null) {
                clearInterval($timer);
            }
        });
        $sse->on('start', function () use ($sse, $app, &$timer) {
            $count = 0;
            $sse->send(['connected' => true], ['event' => 'ready']);
            $timer = $app->worker->tick(250, function () use ($sse, &$count) {
                $sse->send(
                    ['tick' => ++$count],
                    ['event' => 'tick', 'id' => (string) $count],
                );
                if ($count === 3) {
                    $sse->stop();
                }
            });
            if ($timer === false) {
                $timer = null;
                $sse->stop();
            }
        });
        return $sse;
    });
    $app->get('/subscribe', function (
        Request $request,
        Response $response,
    ) use ($app) {
        $events = $app->container(EventEmitter::class);
        $sse = $response->sse();
        $listener = fn(Event $event) => $sse->send($event->data, [
            'event' => 'message',
        ]);
        $events->on('message', $listener);
        $request
            ->connection()
            ->defer(fn() => $events->off('message', $listener));
        $sse->on(
            'start',
            fn() => $sse->send(['connected' => true], ['event' => 'ready']),
        );
        return $sse;
    });
    $app->post('/publish', function (Request $request) use ($app) {
        $data = $request->validate(['message' => 'required|string']);
        $app->container(EventEmitter::class)->emit('message', $data);
        return ['published' => true];
    });
    return $app;
};
