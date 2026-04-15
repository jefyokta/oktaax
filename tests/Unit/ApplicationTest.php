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
    );

    class DummyResult {}

    $router = new Router();
    $router->get('/custom', function () {
        return new DummyResult();
    });

    $application = Application::create($request, $response);

    class HelloInvokable
    {
        public function __invoke()
        {
            return 'world';
        }
    }

    class HelloRespInvokable
    {
        public function __invoke()
        {
            return 'worldresp';
        }
    }

    $application->inject(Request::class, 'hello', HelloInvokable::class);

    $application->inject(OktaaxResponse::class, 'helloResp', HelloRespInvokable::class);

    expect($request->hello())->toBe('world');
    expect($response->helloResp())->toBe('worldresp');
});
