<?php

use Oktaax\Oktaax;
use Oktaax\Http\Routing\Router;
use Oktaax\Http\Routing\Route;

it('can set multiple server options using setServer', function () {
    $app = new Oktaax();

    $app->setServer('worker_num', 2);
    $app->setServer(['daemonize' => false]);

    $settings = $app->config('server');

    expect($settings)->toBeArray();
    expect($settings)->toHaveKey('worker_num');
    expect($settings['worker_num'])->toBe(2);
    expect($settings)->toHaveKey('daemonize');
    expect($settings['daemonize'])->toBeFalse();
});

it('registers routes on its owned router', function () {
    $app = new Oktaax();

    $app->get('/ping', fn() => 'pong');

    [$route] = $app->router()->resolve('/ping', 'GET');

    expect($route)->toBeInstanceOf(Route::class);
    expect($route->getPath())->toBe('/ping');
});

it('populates route cache when finding handler', function () {
    $app = new Oktaax();
    $app->get('/cache-test', fn() => 'ok');

    $route = $app->router()->resolve('/cache-test', 'GET');
    $route2 = $app->router()->resolve('/cache-test', 'GET');

    expect($route2)->toBe($route);
});
