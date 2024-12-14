<?php

use Example\Controller\HomeController;
use Oktaax\Console;
use Oktaax\Http\Middleware\Logger;
use Oktaax\Oktaax;

require_once __DIR__ . "/../vendor/autoload.php";

require_once "controller/HomeController.php";

$app = new Oktaax;

$app->setServer('pid_file', 'swoole.pid');

$app->useBlade(__DIR__ . '/resources/views/', __DIR__ . "/storage/views/")
    ->use(Logger::handle())
    ->get('/', [HomeController::class, 'index'])
    // ->withSSL("", "")
    ->listen(3000, function ($url) {
        Console::info("Server Started at $url");
    });
