<?php

use Example\Controller\HomeController;
use Oktaax\Console;
use Oktaax\Http\Middleware\Logger;
use Oktaax\Oktaax;

require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/app/Controller/HomeController.php";




$app = new Oktaax;

//required if you want to using hot reload
$app->setServer('pid_file','swoole.pid');


$app->set('viewsDir', __DIR__ . '/resources/views/');
$app->useBlade(__DIR__ . '/resources/views/', __DIR__ . "/storage/views/")
    ->use(Logger::handle())
    ->get('/', [HomeController::class, 'index'])
    ->listen(3000, function ($url) {
        Console::info("Server Started at $url");
    });

//(optional) if you using hot reload
return $app;
