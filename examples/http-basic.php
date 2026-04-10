<?php
require __DIR__ . "/../vendor/autoload.php";

use Oktaax\Http\Request;
use Oktaax\Http\Response;
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
        'query' => $request->query() ?? [],
    ]);
});

$app->post('/submit', function (Request $request, Response $response) {
    $response->json([
        'received' => $request->all(),
        'timestamp' => time(),
    ]);
});

$app->listen(8000);
