<?php

use Oktaax\Http\Request;
use Oktaax\Oktaax;
use Swoole\Coroutine;

require_once __DIR__."/../vendor/autoload.php";


$app = new Oktaax();


$app->get("/delay/{delay}", function (Request $request) {
    Coroutine::sleep($request->params['delay']);
    return [
        "request" => $request->all(),
        "delay" => $request->params['delay'],
        "headers"=>$request->headers
    ];
});
$app->setServer("worker_num", 2);

$app->listen(3001,function ($url) {
    echo $url;
});
