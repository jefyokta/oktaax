<?php

use Oktaax\Application;
use Oktaax\Http\Request;
use Oktaax\Http\Response;
use Tests\Support\Coroutines;
use Tests\Support\HttpFixture;

it('does not retain request or response context after handling', function () {
    Coroutines::run(function () {
        $app = new Application();
        $app->get('/ping', fn() => 'pong');
        $http = new HttpFixture(uri: '/ping');
        $app->handleRequest($http->request, $http->response);
        expect($http->nativeResponse->sentBody)->toBe('pong');
        expect($app->getRequest())
            ->toBeNull()
            ->and($app->getResponse())
            ->toBeNull();
    });
});

it(
    'binds application helpers and custom result resolvers to the request',
    function () {
        Coroutines::run(function () {
            $app = new Application();
            $app->inject(Request::class, 'hello', fn() => 'world');
            $app->inject(Response::class, 'helloResponse', fn() => 'response');
            $result = new class {};
            $app->respond(
                $result::class,
                fn($value, $req, $res) => $res->json([
                    'request' => $req->hello(),
                    'response' => $res->helloResponse(),
                ]),
            );
            $app->get('/custom', fn() => $result);
            $http = new HttpFixture(uri: '/custom');
            $app->handleRequest($http->request, $http->response);
            expect(json_decode($http->nativeResponse->sentBody, true))->toBe([
                'request' => 'world',
                'response' => 'response',
            ]);
        });
    },
);
