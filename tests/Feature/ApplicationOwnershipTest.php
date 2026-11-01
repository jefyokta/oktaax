<?php

use Oktaax\Application;
use Oktaax\Application\ApplicationContext;
use Oktaax\Contracts\View;
use Oktaax\Exception\HttpException;
use Oktaax\Http\Request;
use Oktaax\Oktaax;
use Tests\Support\Coroutines;
use Tests\Support\FakeRuntime;
use Tests\Support\HttpFixture;

it(
    'requires an explicit execution scope and restores nested scopes after failure',
    function () {
        $verify = function () {
            $first = new Application();
            $second = new Application();
            expect(fn() => \Oktaax\Utils\app())->toThrow(LogicException::class);
            $first->within(function () use ($first, $second) {
                expect(\Oktaax\Utils\app())->toBe($first);
                expect(
                    fn() => $second->within(function () use ($second) {
                        expect(\Oktaax\Utils\app())->toBe($second);
                        throw new RuntimeException('nested failure');
                    }),
                )->toThrow(RuntimeException::class, 'nested failure');
                expect(\Oktaax\Utils\app())->toBe($first);
            });
            expect(fn() => \Oktaax\Utils\app())->toThrow(LogicException::class);
        };
        $verify();
        Coroutines::run($verify);
    },
);

it(
    'does not reset another application configuration, view, or server options',
    function () {
        $a = new Oktaax();
        $a->config()->set('app.name', 'First');
        $a->setServer('worker_num', 7);
        $view = new class implements View {
            public function render(string $view, array $data): ?string
            {
                return 'first-view';
            }
        };
        $a->setView($view);
        $b = new Oktaax();
        expect($a->config('app.name'))
            ->toBe('First')
            ->and($b->config('app.name'))
            ->toBe('Oktaax');
        expect($a->config('server.worker_num'))
            ->toBe(7)
            ->and($b->config('server.worker_num'))
            ->toBe(1);
        expect($a->container(View::class))
            ->toBe($view)
            ->and($b->container(View::class))
            ->not->toBe($view);
        $a->within(function () use ($view) {
            expect(\Oktaax\Utils\app()->config('app.name'))->toBe('First');
            expect(\Oktaax\Utils\app()->container(View::class))->toBe($view);
        });
        expect(fn() => ApplicationContext::current())->toThrow(
            LogicException::class,
        );
    },
);

it(
    'keeps request cleanup local and releases captured requests after completion',
    function () {
        Coroutines::run(function () {
            $app = new Application();
            $deferred = 0;
            $observed = 0;
            $app->afterRequest(function () use (&$observed) {
                $observed++;
            });
            $app->router()->get('/', function ($request) use (
                $app,
                &$deferred,
            ) {
                $request->defer(function () use ($request, &$deferred) {
                    $deferred++;
                });
                return 'ok';
            });
            for ($i = 1; $i <= 3; $i++) {
                $http = new HttpFixture();
                $reference = WeakReference::create($http->request);
                $app->handleRequest($http->request, $http->response);
                expect($deferred)->toBe($i)->and($observed)->toBe($i);
                expect($app->workers()->activeConnections())->toBe(0);
                unset($http);
                gc_collect_cycles();
                expect($reference->get())->toBeNull();
            }
        });
    },
);

it(
    'runs all request finalizers and destroys namespaced context when one fails',
    function () {
        Coroutines::run(function () {
            $app = new Application();
            $trace = [];
            $app->afterRequest(function () {
                throw new RuntimeException('observer failed');
            });
            $app->afterRequest(function () use (&$trace) {
                $trace[] = 'other-observer';
            });
            $app->router()->get('/', function ($request) use ($app, &$trace) {
                $app->context()->set('custom-tenant-value', 'private');
                $request->defer(function () use (&$trace) {
                    $trace[] = 'request-cleanup';
                });
                return 'ok';
            });
            $http = new HttpFixture();
            expect(
                fn() => $app->handleRequest($http->request, $http->response),
            )->toThrow(RuntimeException::class, 'observer failed');
            expect($trace)->toBe(['other-observer', 'request-cleanup']);
            expect($app->context()->get('custom-tenant-value'))
                ->toBeNull()
                ->and($app->getRequest())
                ->toBeNull();
        });
    },
);

it('preserves custom exception handlers when a worker starts', function () {
    Coroutines::run(function () {
        $app = new Application();
        $app->catch(
            HttpException::class,
            fn($e, $req, $res) => $res->status(418)->end('custom'),
        );
        $app->workers()->start(0);
        $http = new HttpFixture(uri: '/unknown');
        $app->handleRequest($http->request, $http->response);
        expect($http->nativeResponse->sentStatus)
            ->toBe(418)
            ->and($http->nativeResponse->sentBody)
            ->toBe('custom');
        $app->workers()->stop();
    });
});

it(
    'keeps overlapping applications, helpers and implicit accessors in their own coroutine',
    function () {
        Coroutines::run(function () {
            $a = new Application(['app' => ['name' => 'A']]);
            $b = new Application(['app' => ['name' => 'B']]);
            $a->inject(Request::class, 'tenant', fn() => 'A');
            $b->inject(Request::class, 'tenant', fn() => 'B');
            $gate = new Swoole\Coroutine\Channel(1);
            $done = new Swoole\Coroutine\Channel(1);
            $a->router()->get('/', function ($req) use ($gate) {
                $gate->pop(2);
                return [
                    'app' => \Oktaax\Utils\app()->config('app.name'),
                    'tenant' => $req->tenant(),
                ];
            });
            $b->router()->get(
                '/',
                fn($req) => [
                    'app' => \Oktaax\Utils\app()->config('app.name'),
                    'tenant' => $req->tenant(),
                ],
            );
            $first = new HttpFixture();
            $second = new HttpFixture();
            Swoole\Coroutine::create(function () use ($a, $first, $done) {
                try {
                    $a->handleRequest($first->request, $first->response);
                    $done->push(true);
                } catch (Throwable $e) {
                    $done->push($e);
                }
            });
            $b->handleRequest($second->request, $second->response);
            $gate->push(true);
            $result = $done->pop(2);
            if ($result instanceof Throwable) {
                throw $result;
            }
            expect($result)->toBeTrue();
            expect(json_decode($first->nativeResponse->sentBody, true))->toBe([
                'app' => 'A',
                'tenant' => 'A',
            ]);
            expect(json_decode($second->nativeResponse->sentBody, true))->toBe([
                'app' => 'B',
                'tenant' => 'B',
            ]);
            expect($a->getRequest())
                ->toBeNull()
                ->and($b->getRequest())
                ->toBeNull();
        });
    },
);

it(
    'freezes application definitions and runs worker and shutdown cleanup in order',
    function () {
        $app = new Application();
        $trace = [];
        $app->container()->worker(
            'resource',
            function () use (&$trace) {
                $trace[] = 'build';
                return new stdClass();
            },
            function () use (&$trace) {
                $trace[] = 'dispose';
            },
        );
        $app->onWorkerStart(function ($worker, $app) use (&$trace) {
            $trace[] = 'worker-start';
            $app->container('resource');
        });
        $app->onWorkerStop(function () use (&$trace) {
            $trace[] = 'worker-stop';
        });
        $app->onShutdown(function () use (&$trace) {
            $trace[] = 'shutdown';
        });
        $app->run(
            new FakeRuntime(function ($app) use (&$trace) {
                $trace[] = 'serve';
                expect($app->state())->toBe('running');
                expect(
                    fn() => $app->config()->set('app.name', 'changed'),
                )->toThrow(LogicException::class);
                expect(
                    fn() => $app->router()->get('/late', fn() => 'late'),
                )->toThrow(LogicException::class);
                expect(fn() => $app->afterRequest(fn() => null))->toThrow(
                    LogicException::class,
                );
            }),
        );
        expect($trace)->toBe([
            'worker-start',
            'build',
            'serve',
            'worker-stop',
            'dispose',
            'shutdown',
        ]);
        expect($app->state())->toBe('stopped');
        $app->shutdown();
        expect(fn() => $app->run(new FakeRuntime(fn() => null)))->toThrow(
            LogicException::class,
        );
    },
);

it(
    'cancels worker timers at shutdown and binds scheduled callbacks to their app',
    function () {
        Coroutines::run(function () {
            $app = new Application(['app' => ['name' => 'timer-owner']]);
            $seen = null;
            $timer = null;
            $app->run(
                new FakeRuntime(function ($app) use (&$seen, &$timer) {
                    $done = new Swoole\Coroutine\Channel(1);
                    \Oktaax\Utils\setTimeout(function () use (&$seen, $done) {
                        $seen = \Oktaax\Utils\app()->config('app.name');
                        $done->push(true);
                    }, 1);
                    $timer = $app->worker->tick(10000, fn() => null);
                    new Application(['app' => ['name' => 'other']]);
                    expect($done->pop(2))->toBeTrue();
                }),
            );
            expect($seen)
                ->toBe('timer-owner')
                ->and(Swoole\Timer::exists($timer))
                ->toBeFalse();
        });
    },
);
