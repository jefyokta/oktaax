<?php

use Oktaax\Http\ResponseEmitter;
use Tests\Support\HttpFixture;

it('commits status and normalized headers once before streaming', function () {
    $http = new HttpFixture();
    $res = $http->response;
    $res->status(201)
        ->header('Content-Type', 'text/plain')
        ->header('content-type', 'application/octet-stream');
    $res->write('first');
    $res->write('second');
    $res->end();

    expect($http->nativeResponse->calls)->toBe([
        'status',
        'header',
        'write',
        'write',
        'end',
    ]);
    expect($http->nativeResponse->sentStatus)->toBe(201);
    expect($http->nativeResponse->sentHeaders)->toBe([
        'content-type' => 'application/octet-stream',
    ]);
    expect($http->nativeResponse->sentBody)->toBe('firstsecond');
    expect(fn() => $res->header('x-late', 'value'))->toThrow(
        LogicException::class,
    );
    expect(fn() => $res->status(202))->toThrow(LogicException::class);
});

it(
    'sends file status and headers before the file and notifies completion',
    function () {
        $http = new HttpFixture();
        $closed = [];
        $http->request->onClose(function ($event) use (&$closed) {
            $closed[] = $event->data;
        });
        $http->response
            ->status(206)
            ->header('x-file', 'yes')
            ->sendfile(__FILE__, 0, 5);

        expect($http->nativeResponse->calls)->toBe([
            'status',
            'header',
            'sendfile',
        ]);
        expect($http->nativeResponse->sentBody)->toBe('<?php');
        expect($closed)->toHaveCount(1);
    },
);

it('stops chunking when a write fails and emits an aborted close', function () {
    $http = new HttpFixture();
    $http->nativeResponse->writeSucceeds = false;
    $aborted = null;
    $http->request->onClose(function ($event) use (&$aborted) {
        $aborted = $event->data['aborted'];
    });
    $http->response->end(str_repeat('x', 20000));

    expect($http->nativeResponse->calls)->toBe(['write']);
    expect($aborted)->toBeTrue();
});

it(
    'stops consuming a generator when the client cannot receive more data',
    function () {
        $http = new HttpFixture();
        $http->nativeResponse->writeSucceeds = false;
        $advanced = false;
        $generator = (function () use (&$advanced) {
            yield 'first';
            $advanced = true;
            yield 'second';
        })();
        (new ResponseEmitter())->dispatch(
            $generator,
            $http->request,
            $http->response,
        );

        expect($advanced)
            ->toBeFalse()
            ->and($http->nativeResponse->calls)
            ->toBe(['write']);
    },
);

it('serializes JSON directly and provides an explicit envelope', function () {
    $plain = new HttpFixture();
    $plain->response->json(['message' => 'kept', 'value' => 1]);
    expect(json_decode($plain->nativeResponse->sentBody, true))->toBe([
        'message' => 'kept',
        'value' => 1,
    ]);

    $envelope = new HttpFixture();
    $envelope->response->jsonEnvelope(['id' => 1], 'created');
    expect(json_decode($envelope->nativeResponse->sentBody, true))->toBe([
        'message' => 'created',
        'error' => [],
        'data' => ['id' => 1],
    ]);

    $invalid = new HttpFixture();
    expect(fn() => $invalid->response->json([NAN]))->toThrow(
        JsonException::class,
    );
});

it('resolves MIME types consistently across requests', function () {
    foreach (range(1, 3) as $_) {
        $http = new HttpFixture();
        $http->response->type('json')->end('{}');
        expect($http->nativeResponse->sentHeaders['content-type'])->toBe(
            'application/json',
        );
    }
});

it('does not emit response body bytes for HEAD requests', function () {
    $http = new HttpFixture(method: 'HEAD');
    $http->response->header('x-head', 'yes')->write('stream');
    $http->response->end('body');

    expect($http->nativeResponse->calls)->toBe(['header', 'end']);
    expect($http->nativeResponse->sentBody)->toBe('');
});

it(
    'reports a disconnected client when a failed write makes the transport unwritable',
    function () {
        $http = new HttpFixture();
        $native = new class extends \Tests\Support\NativeResponse {
            public function write(string $content): bool
            {
                $this->writable = false;
                return false;
            }
        };
        $response = new \Oktaax\Http\Response($native, $http->request);
        $disconnected = 0;
        $http->request->onClientDisconnected(function () use (&$disconnected) {
            $disconnected++;
        });
        expect($response->write('chunk'))->toBeFalse();
        expect($disconnected)->toBe(1);
    },
);
