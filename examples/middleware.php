<?php
require __DIR__ . "/../vendor/autoload.php";

use Oktaax\Http\Request;
use Oktaax\Http\Response;
use Oktaax\Oktaax;

$app = new Oktaax();

$authMiddleware = function (Request $request, Response $response, $next) {
    if ($request->header('x-api-key') !== 'secret-token') {
        $response->status(401)->json(['error' => 'Unauthorized']);
    }

    $next();
};

$app->use($authMiddleware);

$app->get('/private', function (Request $request, Response $response) {
    $response->json([
        'message' => 'You have access to the private route',
        'user_agent' => $request->userAgent(),
    ]);
});

$app->listen(8002);
