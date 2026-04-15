<?php
require __DIR__ . "/../vendor/autoload.php";

use Oktaax\Console;
use Oktaax\Core\Application;
use Oktaax\Http\Request;
use Oktaax\Http\Response;
use Oktaax\Http\Support\Report;
use Oktaax\Oktaax;

$app = new Oktaax();

$app->get('/', function (Request $request, Response $response) {
    $response->end('Hello from Oktaax!');
});

$app->get('/user/{id}', function (Request $request, Response $response) {
    $id = $request->params['id'] ?? 'unknown';
    $response->json([
        'message' => 'User details',
        'user_id' => $id,
        'query' => $request->get ?? [],
    ]);
});

$app->post('/submit', function (Request $request, Response $response) {
    $response->json([
        'received' => $request->all(),
        'timestamp' => time(),
    ]);
});



$app->listen(3000, function (Application $application) {
    $application->finally(function ($req, $res) {
        $report = new Report();
        $log = $report();

        Console::log([...$log, "request" => $req]);
    });
});
