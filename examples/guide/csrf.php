<?php

use Oktaax\Application;
use Oktaax\Http\Middleware\Csrf;
use Oktaax\Http\Request;

return function (array $config = []): Application {
    $app = new Application($config);
    $key = getenv('OKTAAX_APP_KEY') ?: str_repeat('demo-key-', 8);
    $app->use(Csrf::generate($key, 300), Csrf::handle($key));
    $app->get(
        '/',
        fn() => [
            'instructions' =>
                'GET /token, then POST /message with JSON fields _token and message.',
        ],
    );
    $app->get('/token', fn(Request $request) => ['token' => $request->_token]);
    $app->post(
        '/message',
        fn(Request $request) => $request->validate([
            'message' => 'required|string',
        ]),
    );
    return $app;
};
