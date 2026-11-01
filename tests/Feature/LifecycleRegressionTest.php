<?php

use Oktaax\Application;
use Oktaax\Runtime\Swoole\Listener\HttpRequestListener;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Tests\Support\Coroutines;
use Tests\Support\HttpFixture;

it('cleans up context even when a request finalizer throws', function () {
    Coroutines::run(function () {
        $app = new Application();
        $app->get('/cleanup', function ($req) use ($app) {
            $app->context()->set('private-value', true);
            $req->defer(fn() => throw new RuntimeException('cleanup failed'));
            return 'ok';
        });
        $http = new HttpFixture(uri: '/cleanup');
        expect(
            fn() => $app->handleRequest($http->request, $http->response),
        )->toThrow(RuntimeException::class, 'cleanup failed');
        expect($app->getRequest())
            ->toBeNull()
            ->and($app->getResponse())
            ->toBeNull();
        expect($app->context()->get('private-value'))->toBeNull();
    });
});

it(
    'isolates overlapping request lifecycles when another request completes first',
    function () {
        Coroutines::run(function () {
            $app = new Application();
            $release = new Channel(1);
            $completed = new Channel(1);
            $app->get('/a', fn() => 'A');
            $app->get('/b', function () use ($release) {
                $release->pop(2);
                return 'B';
            });
            $a = new HttpFixture(uri: '/a');
            $b = new HttpFixture(uri: '/b');
            Coroutine::create(function () use ($app, $b, $completed) {
                try {
                    $app->handleRequest($b->request, $b->response);
                    $completed->push($app->getRequest() === null);
                } catch (Throwable $e) {
                    $completed->push($e);
                }
            });
            $app->handleRequest($a->request, $a->response);
            $release->push(true);
            $result = $completed->pop(2);
            if ($result instanceof Throwable) {
                throw $result;
            }
            expect($result)->toBeTrue();
            expect($a->nativeResponse->sentBody)
                ->toBe('A')
                ->and($b->nativeResponse->sentBody)
                ->toBe('B');
            expect($app->getRequest())->toBeNull();
        });
    },
);

it('binds native request listeners to their application', function () {
    Coroutines::run(function () {
        $first = new Application();
        $first->get('/', fn() => 'first');
        $listener = new HttpRequestListener($first);
        $second = new Application();
        $second->get('/', fn() => 'second');
        $http = new HttpFixture();
        $listener->handle($http->nativeRequest, $http->nativeResponse);
        expect($http->nativeResponse->sentBody)->toBe('first');
        expect($first->getRequest())->toBeNull();
    });
});

it(
    'emits a 405 response including its Allow header through the kernel',
    function () {
        Coroutines::run(function () {
            $app = new Application();
            $app->get('/only-get', fn() => 'ok');
            $http = new HttpFixture(method: 'POST', uri: '/only-get');
            (new HttpRequestListener($app))->handle(
                $http->nativeRequest,
                $http->nativeResponse,
            );
            expect($http->nativeResponse->sentStatus)->toBe(405);
            expect($http->nativeResponse->sentHeaders['allow'])->toBe('GET');
            expect($app->getResponse())->toBeNull();
        });
    },
);

it(
    'streams configured headers before the first chunk through the emitter',
    function () {
        Coroutines::run(function () {
            $app = new Application();
            $app->get(
                '/stream',
                fn($req, $res) => $res->stream(
                    fn($write) => $write('chunk'),
                    202,
                    ['content-type' => 'text/plain'],
                ),
            );
            $http = new HttpFixture(uri: '/stream');
            $app->handleRequest($http->request, $http->response);
            expect($http->nativeResponse->calls)->toBe([
                'status',
                'header',
                'write',
                'end',
            ]);
            expect($http->nativeResponse->sentBody)->toBe('chunk');
        });
    },
);

it(
    'does not finish a manually sent response again after a failed chunk write',
    function () {
        Coroutines::run(function () {
            $app = new Application();
            $app->get(
                '/failed-write',
                fn($req, $res) => $res->end(str_repeat('x', 20000)),
            );
            $http = new HttpFixture(uri: '/failed-write');
            $http->nativeResponse->writeSucceeds = false;
            $app->handleRequest($http->request, $http->response);
            expect($http->nativeResponse->calls)->toBe(['write']);
            expect($app->getRequest())->toBeNull();
        });
    },
);
