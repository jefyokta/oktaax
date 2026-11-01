<?php

use Oktaax\Application;
use Oktaax\Http\Response\File;
use Oktaax\Websocket\Connection;
use Oktaax\Websocket\Message;

return function (array $config = []): Application {
    $app = new Application($config);
    $ws = $app->websocket('/socket');
    $ws->on(
        'echo',
        fn(Connection $connection, Message $message) => $connection->send(
            'echo',
            $message->data(),
            $message->id(),
        ),
    );
    $ws->on('ping', fn(Connection $connection) => $connection->send('pong'));
    $ws->on('chat.join', function (Connection $connection, Message $message) {
        $data = $message->validate(['room' => 'required|string']);
        // Perform your room authorization here before joining.
        $connection->join($data['room']);
        $connection->send('chat.joined', $data, $message->id());
    });
    $ws->on('chat.send', function (
        Connection $connection,
        Message $message,
    ) use ($ws) {
        $data = $message->validate([
            'room' => 'required|string',
            'text' => 'required|string',
        ]);
        if (!$connection->inRoom($data['room'])) {
            $connection->send('error', ['code' => 'not_in_room']);
            return;
        }
        $ws->room($data['room'])->send('chat.message', [
            'text' => $data['text'],
        ]);
    });
    $app->get(
        '/',
        fn() => new File(__DIR__ . '/views/websocket.html', 200, [
            'content-type' => 'text/html; charset=utf-8',
        ]),
    );
    return $app;
};
