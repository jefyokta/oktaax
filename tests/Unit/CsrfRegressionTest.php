<?php

use Oktaax\Http\Middleware\Csrf;
use Tests\Support\HttpFixture;

it(
    'verifies a CSRF token and returns the downstream result exactly once',
    function () {
        $key = str_repeat('k', 64);
        $http = new HttpFixture(
            method: 'POST',
            post: ['_token' => Csrf::generatetoken($key, 300)],
        );
        $calls = 0;
        $result = Csrf::handle($key)(
            $http->request,
            $http->response,
            function () use (&$calls) {
                $calls++;
                return 'created';
            },
        );

        expect($calls)->toBe(1)->and($result)->toBe('created');
    },
);

it('does not turn a downstream exception into a CSRF failure', function () {
    $key = str_repeat('k', 64);
    $http = new HttpFixture(
        method: 'POST',
        post: ['_token' => Csrf::generatetoken($key, 300)],
    );
    expect(
        fn() => Csrf::handle($key)(
            $http->request,
            $http->response,
            fn() => throw new RuntimeException('handler failed'),
        ),
    )->toThrow(RuntimeException::class, 'handler failed');
    expect($http->nativeResponse->sentStatus)->toBe(200);
});

it('rejects missing or invalid tokens without calling the handler', function (
    $token,
) {
    $http = new HttpFixture(method: 'POST', post: ['_token' => $token]);
    $calls = 0;
    Csrf::handle(str_repeat('k', 64))(
        $http->request,
        $http->response,
        function () use (&$calls) {
            $calls++;
        },
    );

    expect($calls)->toBe(0)->and($http->nativeResponse->sentStatus)->toBe(403);
})->with([null, '', 'invalid', ['invalid']]);

it('passes safe requests and token generation results through', function (
    $method,
) {
    $http = new HttpFixture(method: $method);
    expect(
        Csrf::handle(str_repeat('k', 64))(
            $http->request,
            $http->response,
            fn() => 'safe',
        ),
    )->toBe('safe');
    expect(
        Csrf::generate(str_repeat('k', 64), 300)(
            $http->request,
            $http->response,
            fn() => 'generated',
        ),
    )->toBe('generated');
    expect($http->request->_token)->toBeString();
})->with(['GET', 'HEAD', 'OPTIONS']);
