<?php
require __DIR__ . "/../vendor/autoload.php";

use Oktaax\Console;
use Oktaax\Core\URL;
use Oktaax\Oktaax;
use Oktaax\Trait\HasWebsocket;
use Oktaax\Websocket\Client;
use Oktaax\Websocket\Server;
use Oktaax\Http\Request;
use Oktaax\Http\Response;
use PhpParser\Node\Const_;
use Swoole\Table;

$app = new class extends Oktaax {
    use HasWebsocket;
};

$app->table(function (Table $table) {
    // optional websocket-related table setup
});

$app->ws('message', function (Server $server, Client $client) {
    $payload = $client->data;
    $server->reply([
        'event' => 'message',
        'received' => $payload,
        'timestamp' => time(),
    ]);
});

$app->ws('ping', function (Server $server, Client $client) {
    $server->reply([
        'event' => 'pong',
        'time' => time(),
    ]);
});
$app->get("/ws", function (Response $response) {
    $response->status(101)->end();
});

$app->gate(function($_,Request $request){
    Console::log("incoiming");
    Console::log($request->name);

});

$app->get('/', fn() => "WebSocket server is running. Connect at ws://localhost:8001 and send { event: 'message', data: ... }.");

$app->listen(8001, function (URL $url) {
    echo "HTTP: {$url->getHttpUrl()}\n";
    echo "WS: {$url->getWebsocketUrl()}\n";
});
