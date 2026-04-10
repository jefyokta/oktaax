<?php

use Oktaax\Oktaax;
use Oktaax\Http\Router;
use Oktaax\Http\Route;

beforeEach(function () {
    $router = new ReflectionClass(Router::class);
    $routes = $router->getProperty('routes');
    // $routes->setAccessible(true);
    $routes->setValue(null, []);

    $cache = $router->getProperty('routeCache');
    // $cache->setAccessible(true);
    $cache->setValue(null, []);
});

it('can set multiple server options using setServer', function () {
    $app = new Oktaax();

    $app->setServer(['worker_num' => 2]);
    $app->setServer(['daemonize' => false]);

    $property = (new ReflectionClass($app))->getProperty('serverSettings');
    // $property->setAccessible(true);
    $settings = $property->getValue($app);

    expect($settings)->toBeArray();
    expect($settings)->toHaveKey('worker_num');
    expect($settings['worker_num'])->toBe(2);
    expect($settings)->toHaveKey('daemonize');
    expect($settings['daemonize'])->toBeFalse();
});

it('forwards route methods to Router through __call', function () {
    $app = new Oktaax();

    $app->get('/ping', fn() => 'pong');

    $route = Router::findHandler('/ping', 'GET');

    expect($route)->toBeInstanceOf(Route::class);
    expect($route->getPath())->toBe('/ping');
});

it('populates route cache when finding handler', function () {
    $app = new Oktaax();
    $app->get('/cache-test', fn() => 'ok');

    $route = Router::findHandler('/cache-test', 'GET');
    $route2 = Router::findHandler('/cache-test', 'GET');

    expect($route2)->toBe($route);
});
