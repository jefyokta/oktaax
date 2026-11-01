<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Oktaax\Oktaax;
use Oktaax\Websocket\Connection;
use Oktaax\Websocket\Message;

$app = new Oktaax();
$ws = $app->websocket('/socket');
$ws->on(
    'message',
    fn(Connection $connection, Message $message) => $connection->send(
        'message',
        $message->data(),
        $message->id(),
    ),
);
$ws->on('ping', function (Connection $connection) {
    $connection->tick(
        1000,
        fn(Connection $connection) => $connection->send('pong', [
            'time' => time(),
        ]),
    );
});
$app->get(
    '/',
    fn() => 'Connect to ws://127.0.0.1:8001/socket and send {"event":"message","data":"Hello"}.',
);
$app->listen(8001);
