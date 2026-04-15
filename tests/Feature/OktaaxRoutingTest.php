<?php

use Oktaax\Oktaax;
use Oktaax\Http\Router;
use Oktaax\Http\Route;

it('registers and resolves simple routes with Oktaax', function () {
    $app = new Oktaax();

    $app->get('/api/test', fn() => 'ok');

    [$route ]= Router::findHandler('/api/test', 'GET');

    expect($route)->toBeInstanceOf(Route::class);
});

it('resolves dynamic routes with Oktaax and returns the same object', function () {
    $app = new Oktaax();

    $app->get('/api/users/{id}', fn() => 'user');

    [$route] = Router::findHandler('/api/users/123', 'GET');

    expect($route)->toBeInstanceOf(Route::class);
});
