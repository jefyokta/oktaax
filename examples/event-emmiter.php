<?php

use Oktaax\Console;
use Oktaax\Core\Application;
use Oktaax\Event\EventEmitter;
use Oktaax\Oktaax;
use Swoole\Http\Server;

use function Oktaax\Utils\_o;
use function Oktaax\Utils\app;

require __DIR__ . '/../vendor/autoload.php';

$app = new Oktaax();

$app->setServer("worker_num", 4);

$app->get("/dispatch", function () {
    $server = app()->container(Server::class);
    $current_worker_id = app()->worker->id;

    $total_workers = $server->setting['worker_num'] ?? 4;

    $payload = _o(
        from:$current_worker_id,
        message:"hello from $current_worker_id!\n"
    );

    for ($i = 0; $i < $total_workers; $i++) {
        if ($i === $current_worker_id) {
            continue;
        }

        $server->sendMessage($payload, $i);
    }

    return "ok";
});


$app->on('PipeMessage', function (Swoole\Http\Server $server, int $src_worker_id, mixed $message) {

    var_dump($message);
});

$app->listen(3000);
