<?php

use Oktaax\Console;
use Oktaax\Http\Middleware\Logger;
use Oktaax\Http\Request;
use Oktaax\Http\Response;
use Oktaax\Oktaax;

require_once __DIR__ . "/../vendor/autoload.php";




$app = new Oktaax;


$app->set('viewsDir', __DIR__ . '/resources/views/');
$app->useBlade(__DIR__ . '/resources/views/', __DIR__ . "/storage/views/")
    ->use(Logger::handle())
    ->get('/', function (Request $request, Response $response) {

        $response->render("index");
    })
    ->listen(3000, function ($url) {
        Console::info("Server Started at $url");
    });
