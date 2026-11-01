<?php

use Oktaax\Application;
use Oktaax\Http\Inertia;
use Oktaax\Http\Request;
use Oktaax\Http\Response;
use Oktaax\View\PhpView;

return function (array $config = []): Application {
    $app = new Application($config);
    $app->setView(new PhpView(__DIR__ . '/views'));
    $app->config()->set('inertia.base_view', 'inertia');
    $app->get('/', function (Request $request, Response $response) {
        if (!$request->isInertia()) {
            $response->type('html');
        }
        Inertia::render('Welcome', [
            'name' => $request->query('name', 'friend'),
        ]);
    });
    return $app;
};
