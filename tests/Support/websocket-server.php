<?php

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Oktaax\Application;
use Oktaax\Http\Request;
use Oktaax\Runtime\Swoole\ProcessRuntime;
use Oktaax\Websocket\Connection;
use Oktaax\Websocket\Message;

$class = $argv[1] === 'fluent' ? \Oktaax\Oktaax::class : Application::class;
$app = new $class([
    'app' => [
        'host' => '127.0.0.1',
        'port' => (int) $argv[2],
        'name' => 'ws-test',
    ],
    'server' => [
        'worker_num' => $argv[1] === 'multi' ? 2 : 1,
        'log_file' => $argv[3],
    ],
]);
$app->container()->worker(
    'ws-state',
    fn() => (object) [
        'opened' => 0,
        'closed' => 0,
        'cleaned' => 0,
        'messages' => 0,
    ],
);
$ws = $app
    ->websocket('/ws')
    ->maxMessageBytes(256)
    ->origins(['https://allowed.test']);
$ws->authorize(fn(Request $request) => $request->cookie('auth') === 'demo');
$ws->onOpen(function (Connection $connection) use ($app) {
    $state = $app->container('ws-state');
    $state->opened++;
    $connection->set('sequence', 0);
    $connection->defer(function () use ($state) {
        $state->cleaned++;
    });
    $connection->tick(10000, fn() => null);
    $connection->send('ready', [
        'worker' => $app->worker->id,
        'request_is_null' => $app->getRequest() === null,
    ]);
});
$ws->onClose(function () use ($app) {
    $app->container('ws-state')->closed++;
});
$ws->use(function ($connection, Message $message, $next) use ($app) {
    $state = $app->container('ws-state');
    $message->defer(function () use ($state) {
        $state->messages++;
    });
    return $next();
});
$ws->on('echo', function (Connection $connection, Message $message) {
    $sequence = $connection->get('sequence') + 1;
    $connection->set('sequence', $sequence);
    $connection->send(
        'echo',
        ['payload' => $message->data(), 'sequence' => $sequence],
        $message->id(),
    );
});
$ws->on(
    'validate',
    fn(Connection $connection, Message $message) => $connection->send(
        'valid',
        $message->validate(['text' => 'required|string']),
        $message->id(),
    ),
);
$ws->on('join', function (Connection $connection, Message $message) {
    $data = $message->validate(['room' => 'required|string']);
    $connection->join($data['room']);
    $connection->send('joined', $data);
});
$ws->on('publish', function (Connection $connection, Message $message) use (
    $ws,
) {
    $data = $message->validate([
        'room' => 'required|string',
        'text' => 'required|string',
    ]);
    if (!$connection->inRoom($data['room'])) {
        $connection->send('error', ['code' => 'not_in_room']);
        return;
    }
    $ws->room($data['room'])->send('news', ['text' => $data['text']]);
});
$ws->on('all', fn() => $ws->broadcast('all', ['ok' => true]));
$ws->on(
    'later',
    fn(Connection $connection) => $connection->after(
        5,
        fn(Connection $connection) => $connection->send('later', [
            'app' => \Oktaax\Utils\app()->config('app.name'),
        ]),
    ),
);
$ws->on('fail', fn() => throw new DomainException('private diagnostic'));
$ws->on('bye', fn(Connection $connection) => $connection->close(1000, 'bye'));
$app->websocket('/public')->on(
    'echo',
    fn(Connection $connection, Message $message) => $connection->send(
        'echo',
        $message->data(),
    ),
);
$app->get(
    '/state',
    fn() => get_object_vars($app->container('ws-state')) + [
        'connections' => $app->websockets()->connections()->count(),
    ],
);
$app->get('/native', fn() => ['class' => $app->nativeServer()::class]);
$ready = $argv[4];
$app->onWorkerStart(fn() => file_put_contents($ready, 'ready'));
$app->onWorkerStop(function ($worker) use ($app) {
    error_log(
        'WS_CLEANUP ' .
            json_encode(
                get_object_vars($app->container('ws-state')) + [
                    'active' => $app->websockets()->connections()->count(),
                    'worker' => $worker->id,
                ],
            ),
    );
});
$app->onShutdown(fn() => file_put_contents($ready . '.stopped', 'stopped'));
if ($argv[1] === 'fluent') {
    $app->listen((int) $argv[2]);
} else {
    $app->run(new ProcessRuntime());
}
