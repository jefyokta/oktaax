<?php

use Oktaax\Console;
use Oktaax\Core\Application;
use Oktaax\Event\EventEmitter;
use Oktaax\Oktaax;
use Swoole\Http\Server;

use function Oktaax\Utils\_o;
use function Oktaax\Utils\app;

require __DIR__ . '/../vendor/autoload.php';


$emiter = new EventEmitter;


$emiter->on("test", function ($e) {
    throw new Error("test");
});

$emiter->on("test", function ($e) {
    // throw new Error("test");
    Console::log("test");
});
$emiter->on("test", function ($e) {
    throw new Error("test2");
});

$emiter->emit("test");
