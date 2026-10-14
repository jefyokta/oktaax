<?php

use JefyOkta\PhpPromise\Promise;
use Oktaax\Core\Application;
use Oktaax\Core\Container;
use Oktaax\Event\EventEmitter;
use Oktaax\Http\Request;
use Oktaax\Http\Support\SSEResponse;
use Oktaax\Oktaax;
use Swoole\Coroutine;

use function Oktaax\Utils\setTimeout;

require_once __DIR__ . "/../vendor/autoload.php";


$app = new Oktaax;


$app->get("/", function (Request $req) {

    $data = null;

    $req->onClose(function () use (&$data) {
        echo "closed while data " . ($data ?: "is empty") . PHP_EOL;
    });

    Coroutine::sleep(5);
    $data = 'filled';
    return "ok";
});

$app->listen(3000);
