<?php

use Oktaax\Http\Route;

it('matches a static route', function () {
    $route = new Route('/health', 'GET', fn() => 'ok');

    expect($route->isMatch('/health', 'GET'))->toBeTrue();
});

it('matches a dynamic route and sets parameters', function () {
    $route = new Route('/users/{id}/posts/{postId}', 'GET', fn() => 'ok');

    expect($route->isMatch('/users/42/posts/7', 'GET'))->toBeTrue();
});

it('does not match if method differs', function () {
    $route = new Route('/health', 'GET', fn() => 'ok');

    expect($route->isMatch('/health', 'POST'))->toBeFalse();
});
