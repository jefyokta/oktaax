<?php

use Oktaax\Console;
use Oktaax\Oktaax;
use Oktaax\Event\Event;
use Oktaax\Event\EventEmitter;

use function Oktaax\Utils\app;

require __DIR__ . "/../vendor/autoload.php";


$app = new Oktaax;

$app->setServer("worker_num", 2);
$app->get("/", function () {
    app()->worker->send(new Event("test", []));

    return "ok";
});

$app->get("/another", function () {

    app()->worker->send(["test"]);
    return "okok";
});




$app->listen(3000, function ($url) {
    app()->container(EventEmitter::class)->on("test", function ($e) {
        Console::log("received by worker %s, emitted by %s", app()->worker->name, $e->sender['name']);
    });
    if (!app()->worker->isTaskWorker()) {
        app()->worker->onMessage = function (Event $e) {
            Console::log($e);
        };
    }
});
