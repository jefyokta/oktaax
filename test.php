<?php



require_once "vendor/autoload.php";

use OpenSwoole\WebSocket\Server;
use OpenSwoole\Http\Request;
use OpenSwoole\WebSocket\Frame;

$server = new Server("127.0.0.1", 9501);

$server->on("Start", function (Server $server) {
    echo "OpenSwoole WebSocket Server started at http://127.0.0.1:9501\n";
});
$server->on('handshake', function (OpenSwoole\HTTP\Request $request, OpenSwoole\HTTP\Response $response) {

    // Periksa apakah request memiliki header `Sec-WebSocket-Key`
    if (!isset($request->header['sec-websocket-key'])) {
        // Jika tidak ada, tolak handshake
        $response->status(400);
        $response->end();
        return false;
    }

    // Ambil `Sec-WebSocket-Key` dari request header
    $secWebSocketKey = $request->header['sec-websocket-key'];
    // Tambahkan GUID sesuai standar WebSocket
    $secWebSocketAccept = base64_encode(sha1($secWebSocketKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));

    // Set header `Sec-WebSocket-Accept` yang diperlukan
    $response->header('Upgrade', 'websocket');
    $response->header('Connection', 'Upgrade');
    $response->header('Sec-WebSocket-Accept', $secWebSocketAccept);

    // Set status sukses untuk handshake
    $response->status(101);
    $response->end();

    echo "authenticated and connected!" . PHP_EOL;
    return true;
});


$server->on('Open', function (Server $server, OpenSwoole\Http\Request $request) {
    echo "connection open: {$request->fd}\n";

    $server->tick(1000, function () use ($server, $request) {
        $server->push($request->fd, json_encode(["hello", time()]));
    });
});

$server->on('Message', function (Server $server, Frame $frame) {
    echo "received message: {$frame->data}\n";

    $server->push($frame->fd, json_encode(["hello", time()]));
});

$server->on('Close', function (Server $server, int $fd) {
    echo "connection close: {$fd}\n";
});

$server->start();
