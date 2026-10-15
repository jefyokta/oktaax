<?php

use Oktaax\Console;
use Oktaax\Http\Request;
use Oktaax\Oktaax;
use Swoole\Event;
use Swoole\Http\Server;

use function Oktaax\Utils\app;

require_once __DIR__ . "/../vendor/autoload.php";


$app = new Oktaax;

$map = new WeakMap();

$app->get("/", function (Request $req) use ($map) {
    $map[$req] = $req->fd;

   

    return "ok";
});

$app->listen(3000);
