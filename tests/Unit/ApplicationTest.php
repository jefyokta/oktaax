<?php

use Oktaax\Core\Application;
use Oktaax\Http\Request;
use Oktaax\Http\Response as OktaaxResponse;
use Oktaax\Http\Router;
use Oktaax\Types\OktaaxConfig;
use Oktaax\Types\AppConfig;
use Oktaax\Interfaces\View;

class FakeSwooleResponse extends \Swoole\Http\Response
{
    public function isWritable(): bool
    {
        return true;
    }
    /** @disregard
     */
    public function header(string $key, array|string $value, bool $format = true): bool
    {
        return true;
    }
    /** @disregard
     */
    public function status(int $http_code, string $reason = ''): bool
    {
        return true;
    }
    /** @disregard
     */
    public function end(?string $content = null): bool
    {
        return true;
    }
}

class FakeRequest extends Request
{
    public function getMethod(): string
    {
        return 'GET';
    }
}

beforeEach(function () {
    $router = new ReflectionClass(Router::class);
    $routes = $router->getProperty('routes');
    $routes->setValue(null, []);

    $cache = $router->getProperty('routeCache');
    $cache->setValue(null, []);
});

it('does not leak request/response after handle', function () {
    $swooleRequest = new \Swoole\Http\Request();
    $swooleRequest->get = [];
    $swooleRequest->post = [];
    $swooleRequest->cookie = [];
    $swooleRequest->server = ['request_uri' => '/ping', 'request_method' => 'GET'];

    $swooleResponse = new FakeSwooleResponse();

    $request = new FakeRequest($swooleRequest);
    $response = new OktaaxResponse(
        $swooleResponse,
        $request,
        new OktaaxConfig(
            $this->createMock(View::class),
            'log',
            false,
            null,
            null,
            new AppConfig(null, false, 300, 'Oktaax'),
            'public/'
        )
    );

    $router = new Router();
    $router->get('/ping', fn() => 'pong');

    \Swoole\Coroutine::create(function () use ($request, $response) {
        Application::create($request, $response)->handle();
        expect(Application::getRequest())->toBeNull();
        expect(Application::getResponse())->toBeNull();
    });
});

it('lets application inject request/response helpers, register catcher and respond', function () {
    $swooleRequest = new \Swoole\Http\Request();
    $swooleRequest->get = [];
    $swooleRequest->post = [];
    $swooleRequest->cookie = [];
    $swooleRequest->server = ['request_uri' => '/custom', 'request_method' => 'GET'];

    $swooleResponse = new FakeSwooleResponse();

    $request = new FakeRequest($swooleRequest);
    $response = new OktaaxResponse(
        $swooleResponse,
        $request,
        new OktaaxConfig(
            $this->createMock(View::class),
            'log',
            false,
            null,
            null,
            new AppConfig(null, false, 300, 'Oktaax'),
            'public/'
        )
    );

    class DummyResult {}

    $router = new Router();
    $router->get('/custom', function () {
        return new DummyResult();
    });

    $application = Application::create($request, $response);

    class HelloInvokable
    {
        public function __invoke($args)
        {
            return 'world';
        }
    }

    class HelloRespInvokable
    {
        public function __invoke($args)
        {
            return 'worldresp';
        }
    }

    $application->inject(Request::class, 'hello', HelloInvokable::class);

    $application->inject(OktaaxResponse::class, 'helloResp', HelloRespInvokable::class);

    expect($request->hello())->toBe('world');
    expect($response->helloResp())->toBe('worldresp');
});

it('can register response handler and exception catcher through application', function () {
    $swooleRequest = new \Swoole\Http\Request();
    $swooleRequest->get = [];
    $swooleRequest->post = [];
    $swooleRequest->cookie = [];
    $swooleRequest->server = ['request_uri' => '/fail', 'request_method' => 'GET'];

    $swooleResponse = new FakeSwooleResponse();
    $request = new FakeRequest($swooleRequest);
    $response = new OktaaxResponse(
        $swooleResponse,
        $request,
        new OktaaxConfig(
            $this->createMock(View::class),
            'log',
            false,
            null,
            null,
            new AppConfig(null, false, 300, 'Oktaax'),
            'public/'
        )
    );

    $router = new Router();
    $router->get('/fail', function () {
        throw new \InvalidArgumentException('boom');
    });
    $router->get('/custom', function () {
        return new \stdClass();
    });

    $application = Application::create($request, $response);

    $caughtMessage = null;
    $caught = false;
    $application->catch(\InvalidArgumentException::class, function ($e) use (&$caughtMessage, &$caught, $response) {
        $caughtMessage = $e->getMessage();
        $caught = true;
        $response->end('caught');
    });

    \Swoole\Coroutine::create(function () use ($application, &$caughtMessage, &$caught, $request, $response) {
        $ctx = \Swoole\Coroutine::getContext();
        $ctx['request'] = $request;
        $ctx['response'] = $response;

        $application->handle();

        expect($caughtMessage)->toBe('boom');
        expect($caught)->toBeTrue();
    });

    $swooleRequest2 = new \Swoole\Http\Request();
    $swooleRequest2->get = [];
    $swooleRequest2->post = [];
    $swooleRequest2->cookie = [];
    $swooleRequest2->server = ['request_uri' => '/custom', 'request_method' => 'GET'];

    $swooleResponse2 = new FakeSwooleResponse();
    $request2 = new FakeRequest($swooleRequest2);
    $response2 = new OktaaxResponse(
        $swooleResponse2,
        $request2,
        new OktaaxConfig(
            $this->createMock(View::class),
            'log',
            false,
            null,
            null,
            new AppConfig(null, false, 300, 'Oktaax'),
            'public/'
        )
    );

    $application2 = Application::create($request2, $response2);
    $responded = false;
    $application2->respond(\stdClass::class, function ($payload, $req, $res) use (&$responded) {
        $responded = true;
        $res->end('responded');
    });

    \Swoole\Coroutine::create(function () use ($application2, $request2, $response2, &$responded) {
        $ctx = \Swoole\Coroutine::getContext();
        $ctx['request'] = $request2;
        $ctx['response'] = $response2;

        $application2->handle();

        expect($responded)->toBeTrue();
    });
});
