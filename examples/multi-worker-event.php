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




$app->listen(3000, function ($url) {
    app()->container(EventEmitter::class)->on("test",function ($e) {
        Console::log("emitted to worker",app()->worker->name);
    });
});
