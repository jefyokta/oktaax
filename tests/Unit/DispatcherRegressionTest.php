<?php

use Oktaax\Http\ExceptionHandler;
use Oktaax\Http\ResponseEmitter;
use Tests\Support\HttpFixture;

it(
    'resolves result interfaces and does not share resolvers between dispatchers',
    function () {
        $http = new HttpFixture();
        $dispatcher = new ResponseEmitter();
        $resolved = null;
        // Use a non-Traversable interface so this exercises custom result resolution.
        $value = new class implements Stringable {
            public function __toString(): string
            {
                return 'value';
            }
        };
        $dispatcher->register(Stringable::class, function ($result) use (
            &$resolved,
        ) {
            $resolved = (string) $result;
        });
        $dispatcher->dispatch($value, $http->request, $http->response);
        expect($resolved)->toBe('value');
        expect(
            fn() => (new ResponseEmitter())->dispatch(
                $value,
                $http->request,
                $http->response,
            ),
        )->toThrow(RuntimeException::class, 'Unhandled return type');
    },
);

it('serializes Traversable values as their contents', function () {
    $http = new HttpFixture();
    (new ResponseEmitter())->dispatch(
        new ArrayObject(['name' => 'Ada']),
        $http->request,
        $http->response,
    );
    expect(json_decode($http->nativeResponse->sentBody, true))->toBe([
        'name' => 'Ada',
    ]);
});

it('matches inherited exception types and keeps handlers local', function () {
    $dispatcher = new ExceptionHandler();
    $seen = null;
    $dispatcher->register(RuntimeException::class, function ($error) use (
        &$seen,
    ) {
        $seen = $error;
    });
    $error = new OverflowException('overflow');
    $dispatcher->dispatch($error);
    expect($seen)->toBe($error);
    expect(fn() => (new ExceptionHandler())->dispatch($error))->toThrow(
        OverflowException::class,
    );
});
