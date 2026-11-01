<?php

use Oktaax\Application;
use Oktaax\Exception\HttpException;
use Oktaax\Http\Request;
use Oktaax\Http\Response;

return function (array $config = []): Application {
    $app = new Application($config);
    $app->use(function (Request $request, Response $response, callable $next) {
        $response->header('x-request-id', bin2hex(random_bytes(8)));
        return $next();
    });
    $app->get(
        '/',
        fn() => [
            'example' => 'http',
            'routes' => ['/health', '/users/42', 'POST /users', '/private'],
        ],
    );
    $app->get('/health', fn() => ['ok' => true]);
    $app->get(
        '/users/{id:\d+}',
        fn(Request $request) => ['id' => $request->param('id')],
    );
    $app->post('/users', function (Request $request, Response $response) {
        $user = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email',
        ]);
        $response->status(201)->json($user);
    });
    $app->middleware(function (
        Request $request,
        Response $response,
        callable $next,
    ) {
        // A fixed token is only a demonstration. Supply real authentication in your app.
        if ($request->header('x-demo-token') !== 'demo') {
            throw new HttpException(401);
        }
        return $next();
    }, fn($router) => $router->get('/private', fn() => ['authenticated' => true]));
    return $app;
};
