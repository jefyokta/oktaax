<?php

use Oktaax\Application;
use Oktaax\Http\Response\SseResponse;
use Tests\Support\Coroutines;
use Tests\Support\HttpFixture;

it(
    'requires explicit SSE binding and binds a returned value to its response',
    function () {
        Coroutines::run(function () {
            $app = new Application();
            $sse = new SseResponse();
            $app->get('/', function () use ($sse) {
                expect(fn() => $sse->start())->toThrow(
                    RuntimeException::class,
                    'requires an HTTP response',
                );
                return $sse;
            });
            $http = new HttpFixture();
            try {
                $app->handleRequest($http->request, $http->response);
                expect($sse->isStarted())->toBeTrue();
                expect($sse->send('bound'))->toBeTrue();
                expect($http->nativeResponse->sentBody)->toBe(
                    "data: bound\n\n",
                );
            } finally {
                $sse->stop();
            }
        });
    },
);

it(
    'cancels request connection polling timers when the response ends',
    function () {
        Coroutines::run(function () {
            $http = new HttpFixture();
            $closed = 0;
            $http->request->onClose(function () use (&$closed) {
                $closed++;
            }, true);
            $timers = iterator_to_array(Swoole\Timer::list(), false);
            try {
                expect($timers)->not->toBe([]);
                $http->response->end('ok');
                $http->request->close();
                expect($closed)->toBe(1);
                foreach ($timers as $timer) {
                    expect(Swoole\Timer::exists($timer))->toBeFalse();
                }
            } finally {
                foreach ($timers as $timer) {
                    if (Swoole\Timer::exists($timer)) {
                        Swoole\Timer::clear($timer);
                    }
                }
            }
        });
    },
);

it(
    'keeps SSE cleanup in connection scope after request context is destroyed',
    function () {
        Coroutines::run(function () {
            $app = new Application();
            $after = 0;
            $closed = 0;
            $subscriptionReleased = 0;
            $sse = null;
            $app->afterRequest(function () use (&$after) {
                $after++;
            });
            $app->onResponseClosed(function () use (&$closed) {
                $closed++;
            });
            $app->router()->get('/sse', function ($req, $res) use (
                &$sse,
                &$subscriptionReleased,
            ) {
                $req->connection()->defer(function () use (
                    &$subscriptionReleased,
                ) {
                    $subscriptionReleased++;
                });
                return $sse = $res->sse();
            });
            $http = new HttpFixture(uri: '/sse');
            try {
                $app->handleRequest($http->request, $http->response);
                expect($after)
                    ->toBe(1)
                    ->and($closed)
                    ->toBe(0)
                    ->and($subscriptionReleased)
                    ->toBe(0);
                expect($app->getRequest())
                    ->toBeNull()
                    ->and($app->workers()->activeConnections())
                    ->toBe(1);
                expect($sse->send(['message' => 'hello']))->toBeTrue();
                $sse->stop();
                $sse->stop();
                expect($closed)->toBe(1)->and($subscriptionReleased)->toBe(1);
                expect($app->workers()->activeConnections())->toBe(0);
            } finally {
                $sse?->stop();
            }
        });
    },
);

it(
    'notifies disconnect and closes SSE once when the client disappears',
    function () {
        Coroutines::run(function () {
            $app = new Application();
            $sse = null;
            $disconnect = 0;
            $closed = 0;
            $app->router()->get('/', function ($req, $res) use (
                &$sse,
                &$disconnect,
                &$closed,
            ) {
                $req->onClientDisconnected(function () use (&$disconnect) {
                    $disconnect++;
                });
                $sse = $res->sse();
                $sse->on('close', function () use (&$closed) {
                    $closed++;
                });
                return $sse;
            });
            $http = new HttpFixture();
            $http->request->fd = 42;
            try {
                $app->handleRequest($http->request, $http->response);
                $http->nativeResponse->writable = false;
                $app->workers()->disconnect(42);
                $app->workers()->disconnect(42);
                expect($disconnect)
                    ->toBe(1)
                    ->and($closed)
                    ->toBe(1)
                    ->and($sse->isStopped())
                    ->toBeTrue();
                expect($app->workers()->activeConnections())->toBe(0);
            } finally {
                $app->workers()->stop();
            }
        });
    },
);

it(
    'closes active streams and their resources during worker shutdown',
    function () {
        Coroutines::run(function () {
            $app = new Application();
            $sse = null;
            $aborted = null;
            $disconnect = 0;
            $app->router()->get('/', function ($req, $res) use (
                &$sse,
                &$aborted,
                &$disconnect,
            ) {
                $req->onClose(function ($e) use (&$aborted) {
                    $aborted = $e->data['aborted'];
                });
                $req->onClientDisconnected(function () use (&$disconnect) {
                    $disconnect++;
                });
                return $sse = $res->sse();
            });
            $http = new HttpFixture();
            try {
                $app->handleRequest($http->request, $http->response);
                $app->workers()->stop();
                expect($sse->isStopped())
                    ->toBeTrue()
                    ->and($aborted)
                    ->toBeTrue();
                expect($disconnect)->toBe(0);
                expect($app->workers()->activeConnections())
                    ->toBe(0)
                    ->and($http->nativeResponse->writable)
                    ->toBeFalse();
            } finally {
                $app->workers()->stop();
            }
        });
    },
);

it(
    'runs delayed connection callbacks in their owning application',
    function () {
        Coroutines::run(function () {
            $a = new Application(['app' => ['name' => 'owner']]);
            $seen = null;
            $sse = null;
            $a->router()->get('/', function ($req, $res) use (&$seen, &$sse) {
                $req->onClose(function () use (&$seen) {
                    $seen = \Oktaax\Utils\app()->config('app.name');
                });
                return $sse = $res->sse();
            });
            $http = new HttpFixture();
            try {
                $a->handleRequest($http->request, $http->response);
                $b = new Application(['app' => ['name' => 'other']]);
                $b->within(fn() => $sse->stop());
                expect($seen)->toBe('owner');
            } finally {
                $a->workers()->stop();
            }
        });
    },
);

it(
    'drains background timers while letting an active ordinary request finish before service disposal',
    function () {
        Coroutines::run(function () {
            $app = new Application();
            $disposed = false;
            $app->container()->worker(
                'resource',
                fn() => new stdClass(),
                function () use (&$disposed) {
                    $disposed = true;
                },
            );
            $app->workers()->start(0);
            $resource = $app->container('resource');
            $timer = $app->worker->tick(10000, fn() => null);
            $gate = new Swoole\Coroutine\Channel(1);
            $done = new Swoole\Coroutine\Channel(1);
            $app->get('/', function () use ($gate) {
                $gate->pop(2);
                return 'completed';
            });
            $http = new HttpFixture();
            try {
                Swoole\Coroutine::create(function () use ($app, $http, $done) {
                    try {
                        $app->handleRequest($http->request, $http->response);
                        $done->push(true);
                    } catch (Throwable $error) {
                        $done->push($error);
                    }
                });
                $app->workers()->drain();
                expect(Swoole\Timer::exists($timer))->toBeFalse();
                expect($http->response->isWritable())
                    ->toBeTrue()
                    ->and($disposed)
                    ->toBeFalse();
                expect($app->container('resource'))->toBe($resource);
                $gate->push(true);
                $result = $done->pop(2);
                if ($result instanceof Throwable) {
                    throw $result;
                }
                expect($result)
                    ->toBeTrue()
                    ->and($http->nativeResponse->sentBody)
                    ->toBe('completed');
            } finally {
                $app->workers()->stop();
            }
            expect($disposed)->toBeTrue();
        });
    },
);
