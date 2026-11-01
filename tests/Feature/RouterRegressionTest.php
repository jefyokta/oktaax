<?php

use Oktaax\Contracts\Middleware;
use Oktaax\Exception\HttpException;
use Oktaax\Http\Request;
use Oktaax\Http\Response;
use Oktaax\Http\Routing\Router;
use Oktaax\Oktaax;
use Tests\Support\HttpFixture;

it(
    'dispatches dynamic routes with constraints and optional segments',
    function () {
        $router = new Router();
        $router->get('/users/{id:\d+}[/{tab}]', fn($req) => $req->params);
        $http = new HttpFixture(uri: '/users/42/history?ignored=yes');

        expect($router->dispatch($http->request, $http->response))->toBe([
            'id' => '42',
            'tab' => 'history',
        ]);
        expect($router->resolve('/users/42', 'GET')[1])->toBe(['id' => '42']);
        expect(fn() => $router->resolve('/users/nope', 'GET'))->toThrow(
            HttpException::class,
            'Not Found',
        );
    },
);

it(
    'returns 405 with allowed methods and 404 for an unknown resource',
    function () {
        $router = new Router();
        $router->get('/users', fn() => 'get');
        $router->post('/users', fn() => 'post');

        try {
            $router->resolve('/users', 'DELETE');
            $this->fail('Expected method not allowed.');
        } catch (HttpException $e) {
            expect($e->getStatusCode())->toBe(405);
            expect(explode(', ', $e->getHeaders()['Allow']))->toBe([
                'GET',
                'POST',
            ]);
        }

        try {
            $router->resolve('/missing', 'GET');
            $this->fail('Expected not found.');
        } catch (HttpException $e) {
            expect($e->getStatusCode())
                ->toBe(404)
                ->and($e->getHeaders())
                ->toBe([]);
        }
    },
);

it('keeps application routes and middleware isolated', function () {
    $first = new Oktaax();
    $first->use(fn($req, $res, $next) => 'first-' . $next());
    $first->get('/same', fn() => 'route');
    $second = new Oktaax();
    $second->get('/same', fn() => 'second');
    $http = new HttpFixture(uri: '/same');

    expect($first->router()->dispatch($http->request, $http->response))->toBe(
        'first-route',
    );
    expect($second->router()->dispatch($http->request, $http->response))->toBe(
        'second',
    );
});

it(
    'reuses compiled routing data and invalidates it after registration',
    function () {
        $router = new Router();
        $router->get('/first', fn() => 'one');
        $compiled = $router->compile();
        expect($router->compile())->toBe($compiled);
        $router->get('/second/{id}', fn() => 'two');

        expect($router->compile())->not->toBe($compiled);
        expect($router->resolve('/first', 'GET')[0])->toBe(
            $router->resolve('/first', 'GET')[0],
        );
        expect($router->resolve('/second/2', 'GET')[1])->toBe(['id' => '2']);
    },
);

it(
    'preserves nested middleware order and restores scopes on exceptions',
    function () {
        $trace = [];
        $middleware = function ($name) use (&$trace) {
            return function ($req, $res, $next) use (&$trace, $name) {
                $trace[] = $name;
                return $next();
            };
        };
        $router = new Router();
        $router->use($middleware('global'));
        $router->middleware($middleware('outer'), function ($r) use (
            $middleware,
        ) {
            $r->middleware(
                $middleware('inner'),
                fn($r) => $r->get('/nested', fn() => 'nested'),
            );
            $r->get('/outer', fn() => 'outer');
            try {
                $r->middleware(
                    $middleware('failed'),
                    fn() => throw new RuntimeException('registration failed'),
                );
            } catch (RuntimeException) {
            }
            $r->get('/recovered', fn() => 'recovered');
        });
        $router->get('/outside', fn() => 'outside');

        foreach (
            [
                '/nested' => ['global', 'outer', 'inner'],
                '/outer' => ['global', 'outer'],
                '/recovered' => ['global', 'outer'],
                '/outside' => ['global'],
            ]
            as $path => $expected
        ) {
            $trace = [];
            $http = new HttpFixture(uri: $path);
            $router->dispatch($http->request, $http->response);
            expect($trace)->toBe($expected);
        }
    },
);

it(
    'supports middleware objects and preserves explicitly repeated middleware',
    function () {
        $middleware = new class implements Middleware {
            public int $calls = 0;
            public function handle(
                Request $request,
                Response $response,
                $next,
            ): mixed {
                $this->calls++;
                return $next();
            }
        };
        $router = new Router();
        $router->use($middleware);
        $router->middleware($middleware, fn($r) => $r->get('/', fn() => 'ok'));
        $http = new HttpFixture();

        expect($router->dispatch($http->request, $http->response))->toBe('ok');
        expect($middleware->calls)->toBe(2);
    },
);

it(
    'matches literal path characters and rejects unsupported methods',
    function () {
        $router = new Router();
        $router->get('/files/{name}.json', fn() => 'ok');
        expect($router->resolve('/files/data.json', 'GET')[1])->toBe([
            'name' => 'data',
        ]);
        expect(fn() => $router->resolve('/files/dataXjson', 'GET'))->toThrow(
            HttpException::class,
            'Not Found',
        );
        expect(fn() => $router->resolve('/files/data.json', 'POST'))->toThrow(
            HttpException::class,
            'Method Not Allowed',
        );
    },
);

it(
    'preserves the optional next callback on terminal route handlers',
    function () {
        $router = new Router();
        $router->get('/', fn($req, $res, $next) => $next() ?? 'done');
        $http = new HttpFixture();

        expect($router->dispatch($http->request, $http->response))->toBe(
            'done',
        );
    },
);

it(
    'invalidates retained low-level collectors and prevents mutation after application boot',
    function () {
        $app = new \Oktaax\Application();
        $collector = $app->router()->collector();
        $app->router()->compile();
        $collector->get('/low-level', fn() => 'ok');
        expect($app->router()->resolve('/low-level', 'GET')[0])->toBeCallable();
        $app->boot();
        expect(fn() => $collector->get('/late', fn() => 'late'))->toThrow(
            LogicException::class,
        );
    },
);
