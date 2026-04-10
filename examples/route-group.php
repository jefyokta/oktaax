<?php
require __DIR__ . "/../vendor/autoload.php";

use Oktaax\Http\Request;
use Oktaax\Http\Response;
use Oktaax\Oktaax;

$app = new Oktaax();

$authMiddleware = function (Request $request, Response $response, $next) {
    if ($request->header('x-api-key') !== 'secret-token') {
        return $response->status(401)->json(['error' => 'Unauthorized']);
    }

    $next();
};

$app->middleware([$authMiddleware], function ($router) {
    $router->get('/api/status', function (Request $request, Response $response) {
        return $response->json(['status' => 'ok']);
    });

    $router->post('/api/data', function (Request $request, Response $response) {
        return $response->json([
            'body' => $request->all(),
            'received_at' => date('c'),
        ]);
    });
});

$app->listen(8003);
