<?php

use Oktaax\Application;
use Oktaax\Event\EventEmitter;
use Oktaax\Exception\HttpException;
use Oktaax\Runtime\Swoole\CoroutineRuntime;
use Oktaax\Runtime\Swoole\Listener\WebSocketHandshakeListener;
use Oktaax\Websocket\Connection;
use Oktaax\Websocket\Message;
use Tests\Support\Coroutines;
use Tests\Support\HttpFixture;
use Tests\Support\WebSocketFixture;

beforeEach(function () {
    $this->fixture = new WebSocketFixture();
    $this->logFile = tempnam(sys_get_temp_dir(), 'oktaax-ws-unit-');
    $this->oldLog = ini_set('error_log', $this->logFile);
});
afterEach(function () {
    try {
        $this->fixture->app->shutdown();
    } finally {
        ini_set('error_log', $this->oldLog);
        unlink($this->logFile);
    }
});

it(
    'keeps endpoint message emitters independent of peers and the public emitter',
    function () {
        $f = $this->fixture;
        $publicCalls = 0;
        $public = new EventEmitter();
        $public->on('echo', function () use (&$publicCalls): void {
            $publicCalls++;
        });
        $f->app->container()->singleton(EventEmitter::class, $public);
        $f->endpoint->on(
            'echo',
            fn(Connection $connection) => $connection->send('first'),
        );
        $other = $f->app->websocket('/other');
        $other->on(
            'echo',
            fn(Connection $connection) => $connection->send('second'),
        );
        expect(fn() => $f->endpoint->on('echo', fn() => null))->toThrow(
            InvalidArgumentException::class,
            'WebSocket event is already registered: echo',
        );
        $f->start();
        $f->connect(1);
        $f->connect(2, '/other');
        $f->app->websockets()->message(1, '{"event":"echo"}');
        $f->app->websockets()->message(2, '{"event":"echo"}');
        expect($publicCalls)
            ->toBe(0)
            ->and($f->transport->messages(1))
            ->toBe([['event' => 'first', 'data' => null]])
            ->and($f->transport->messages(2))
            ->toBe([['event' => 'second', 'data' => null]]);
        $public->emit('echo');
        expect($publicCalls)
            ->toBe(1)
            ->and(count($f->transport->messages(1)))
            ->toBe(1)
            ->and(count($f->transport->messages(2)))
            ->toBe(1);
    },
);

it(
    'preserves middleware results and the original message handler exception',
    function () {
        $f = $this->fixture;
        $results = [];
        $seen = null;
        $failure = new DomainException('Original handler failure.');
        $f->endpoint->use(function ($connection, $message, $next) use (
            &$results,
        ) {
            $results[] = $next();
        });
        $f->endpoint->on('compute', fn() => 42);
        $f->endpoint->on('fail', fn() => throw $failure);
        $f->endpoint->onError(function ($error) use (&$seen): void {
            $seen = $error;
        });
        $f->start();
        $f->connect(1);
        $f->app->websockets()->message(1, '{"event":"compute"}');
        $f->app->websockets()->message(1, '{"event":"fail"}');
        expect($results)
            ->toBe([42])
            ->and($seen)
            ->toBe($failure);
    },
);

it(
    'retains one connection identity and explicit application scope across messages',
    function () {
        $f = $this->fixture;
        $seen = [];
        $f->endpoint->onOpen(
            fn(Connection $connection) => $connection->set('counter', 0),
        );
        $f->endpoint->on('count', function (
            Connection $connection,
            Message $message,
        ) use (&$seen, $f) {
            $seen[] = [$connection, \Oktaax\Utils\app()];
            $connection->set('counter', $connection->get('counter') + 1);
            $connection->send(
                'counted',
                $connection->get('counter'),
                $message->id(),
            );
        });
        $f->start();
        $connection = $f->connect(12);
        $other = new Application();
        $other->within(function () use ($f) {
            $f->app->websockets()->message(12, '{"event":"count","id":"1"}');
            $f->app->websockets()->message(12, '{"event":"count","id":"2"}');
        });
        expect($seen)->toBe([[$connection, $f->app], [$connection, $f->app]]);
        expect($f->transport->messages(12))->toBe([
            ['event' => 'counted', 'data' => 1, 'id' => '1'],
            ['event' => 'counted', 'data' => 2, 'id' => '2'],
        ]);
        expect(fn() => \Oktaax\Utils\app())->toThrow(LogicException::class);
    },
);

it(
    'runs message middleware and releases message cleanup without closing the connection',
    function () {
        $f = $this->fixture;
        $trace = [];
        $reference = null;
        $f->endpoint->use(function ($connection, Message $message, $next) use (
            &$trace,
            &$reference,
        ) {
            $reference = WeakReference::create($message);
            $message->defer(function () use (&$trace) {
                $trace[] = 'message-cleanup';
            });
            $trace[] = 'before';
            $next();
            $trace[] = 'after';
        });
        $f->endpoint->on('save', function ($connection, Message $message) use (
            &$trace,
        ) {
            $trace[] = 'handler';
            $connection->send(
                'saved',
                $message->validate(['text' => 'required|string']),
            );
        });
        $f->start();
        $connection = $f->connect(1);
        $f->app
            ->websockets()
            ->message(
                1,
                '{"event":"save","data":{"text":"hello","admin":true}}',
            );
        expect($trace)->toBe(['before', 'handler', 'after', 'message-cleanup']);
        expect($reference->get())
            ->toBeNull()
            ->and($connection->isOpen())
            ->toBeTrue();
        expect($f->transport->messages(1))->toBe([
            ['event' => 'saved', 'data' => ['text' => 'hello']],
        ]);
        $f->app
            ->websockets()
            ->message(1, '{"event":"save","id":"invalid","data":{}}');
        expect($f->transport->messages(1)[1]['data']['code'])->toBe(
            'validation_failed',
        );
        expect($f->transport->messages(1)[1]['id'])
            ->toBe('invalid')
            ->and($connection->isOpen())
            ->toBeTrue();
    },
);

it(
    'short circuits message middleware and isolates endpoint dispatch',
    function () {
        $f = $this->fixture;
        $f->endpoint->use(
            fn(Connection $connection) => $connection->send('blocked'),
        );
        $f->endpoint->on(
            'event',
            fn() => throw new RuntimeException('Must not run'),
        );
        $other = $f->app->websocket('/other');
        $other->on(
            'event',
            fn(Connection $connection) => $connection->send('other'),
        );
        $f->start();
        $f->connect(1);
        $f->connect(2, '/other');
        $f->app->websockets()->message(1, '{"event":"event"}');
        $f->app->websockets()->message(2, '{"event":"event"}');
        expect($f->transport->messages(1)[0]['event'])->toBe('blocked');
        expect($f->transport->messages(2)[0]['event'])->toBe('other');
    },
);

it(
    'broadcasts to explicit endpoint rooms without mutating previous targets',
    function () {
        $f = $this->fixture;
        $other = $f->app->websocket('/other');
        $f->start();
        $first = $f->connect(1)->join('team');
        $second = $f->connect(2)->join('team');
        $f->connect(3);
        $f->connect(4, '/other')->join('team');
        $room = $f->endpoint->room('team');
        $room->except($first)->send('private', ['ok' => true]);
        expect($f->transport->messages(1))->toBe([]);
        expect($f->transport->messages(2)[0]['event'])->toBe('private');
        $room->send('team');
        expect($f->transport->messages(1)[0]['event'])->toBe('team');
        $second->leave('team');
        $room->send('remaining');
        $f->endpoint->broadcast('all');
        expect(array_column($f->transport->messages(2), 'event'))->toBe([
            'private',
            'team',
            'all',
        ]);
        expect(array_column($f->transport->messages(3), 'event'))->toBe([
            'all',
        ]);
        expect($f->transport->messages(4))->toBe([]);
    },
);

it('returns failed sends and closes each connection exactly once', function () {
    $f = $this->fixture;
    $trace = [];
    $f->endpoint->onClose(function (Connection $connection) use (&$trace) {
        $trace[] = ['close', $connection->get('user'), $connection->isClosed()];
    });
    $f->start();
    $connection = $f->connect(1)->set('user', 'Ada');
    $connection->defer(function () use (&$trace) {
        $trace[] = 'cleanup';
    });
    $f->transport->fail[1] = true;
    expect($connection->send('message'))->toBeFalse();
    $f->app->workers()->disconnect(1);
    $connection->close();
    expect($trace)->toBe(['cleanup', ['close', 'Ada', true]]);
    expect($connection->request())
        ->toBeNull()
        ->and($connection->get('user'))
        ->toBeNull();
    expect($f->app->websockets()->connections()->count())->toBe(0);
    expect($f->transport->closed[1][0])->toBe(1011);
});

it(
    'clears connection timers and all cleanup even when one cleanup throws',
    function () {
        $f = $this->fixture;
        Coroutines::run(function () use ($f) {
            $trace = [];
            $f->start();
            $connection = $f->connect(1);
            $timer = $connection->tick(10000, fn() => null);
            $connection->defer(function () use (&$trace) {
                $trace[] = 'first';
            });
            $connection->defer(
                fn() => throw new RuntimeException('cleanup failed'),
            );
            $connection->close();
            expect($trace)
                ->toBe(['first'])
                ->and(Swoole\Timer::exists($timer))
                ->toBeFalse();
            expect($f->app->workers()->activeConnections())->toBe(0);
        });
    },
);

it(
    'runs delayed work in its owning app and drains active connections on worker exit',
    function () {
        $f = $this->fixture;
        Coroutines::run(function () use ($f) {
            $f->start();
            $connection = $f->connect(1);
            $seen = null;
            $done = new Swoole\Coroutine\Channel(1);
            $connection->after(1, function (Connection $actual) use (
                &$seen,
                $done,
                $connection,
            ) {
                $seen = [\Oktaax\Utils\app(), $actual === $connection];
                $done->push(true);
            });
            expect($done->pop(2))
                ->toBeTrue()
                ->and($seen)
                ->toBe([$f->app, true]);
            $timer = $connection->tick(10000, fn() => null);
            $f->app->workers()->drain();
            expect($connection->isClosed())
                ->toBeTrue()
                ->and(Swoole\Timer::exists($timer))
                ->toBeFalse();
            expect($f->transport->closed[1][0])->toBe(1001);
        });
    },
);

it(
    'handles unknown events without disconnecting and applies endpoint error policy',
    function () {
        $f = $this->fixture;
        $seen = null;
        $f->endpoint->on('fail', fn() => throw new DomainException('private'));
        $f->endpoint->onError(function (
            $error,
            Connection $connection,
            ?Message $message,
        ) use (&$seen) {
            $seen = [$error::class, $message?->event()];
            $connection->send('failed', ['code' => 'custom'], $message?->id());
        });
        $f->start();
        $connection = $f->connect(1);
        $f->app->websockets()->message(1, '{"event":"missing","id":"1"}');
        expect($f->transport->messages(1)[0]['data']['code'])->toBe(
            'unknown_event',
        );
        $f->app->websockets()->message(1, '{"event":"fail","id":"2"}');
        expect($seen)->toBe([DomainException::class, 'fail']);
        expect($f->transport->messages(1)[1])->toBe([
            'event' => 'failed',
            'data' => ['code' => 'custom'],
            'id' => '2',
        ]);
        expect($connection->isOpen())->toBeTrue();
    },
);

it(
    'rejects unsupported runtimes and freezes retained endpoint definitions',
    function () {
        $f = $this->fixture;
        expect(fn() => $f->app->run(new CoroutineRuntime()))->toThrow(
            LogicException::class,
            'does not support WebSocket',
        );
        expect($f->app->state())->toBe('configuring');
        $f->app->boot();
        expect(fn() => $f->endpoint->on('late', fn() => null))->toThrow(
            LogicException::class,
        );
        expect(fn() => $f->endpoint->authorize(fn() => true))->toThrow(
            LogicException::class,
        );
        expect(fn() => $f->endpoint->maxMessageBytes(1))->toThrow(
            LogicException::class,
        );
        expect(fn() => $f->app->websocket('/late'))->toThrow(
            LogicException::class,
        );
    },
);

it(
    'checks origin and authorization before registering a connection',
    function () {
        $f = $this->fixture;
        $f->endpoint
            ->origins(['https://example.test'])
            ->authorize(fn($request) => $request->header('x-auth') === 'yes');
        $f->start();
        expect(fn() => $f->connect(1, '/absent'))->toThrow(
            HttpException::class,
        );
        expect(
            fn() => $f->connect(
                1,
                headers: ['origin' => 'https://evil.test', 'x-auth' => 'yes'],
            ),
        )->toThrow(HttpException::class);
        expect(
            fn() => $f->connect(
                1,
                headers: ['origin' => 'https://example.test'],
            ),
        )->toThrow(HttpException::class);
        expect($f->app->websockets()->connections()->count())->toBe(0);
        expect(
            $f
                ->connect(
                    1,
                    headers: [
                        'origin' => 'https://example.test',
                        'x-auth' => 'yes',
                    ],
                )
                ->isOpen(),
        )->toBeTrue();
    },
);

it('rejects invalid upgrade headers before running authorization', function () {
    $f = $this->fixture;
    $called = false;
    $f->endpoint->authorize(function () use (&$called) {
        $called = true;
        return true;
    });
    $f->start();
    $http = new HttpFixture(uri: '/ws', headers: ['upgrade' => 'websocket']);
    $listener = new WebSocketHandshakeListener($f->app);
    expect(
        $listener->handle($http->nativeRequest, $http->nativeResponse),
    )->toBeFalse();
    expect($http->nativeResponse->sentStatus)
        ->toBe(400)
        ->and($called)
        ->toBeFalse();
});

it(
    'isolates endpoint definitions and connection registries between applications',
    function () {
        $first = $this->fixture;
        $second = new WebSocketFixture();
        $first->endpoint->on(
            'who',
            fn(Connection $connection) => $connection->send('first'),
        );
        $second->endpoint->on(
            'who',
            fn(Connection $connection) => $connection->send('second'),
        );
        try {
            $first->start();
            $second->start();
            $first->connect(1);
            $second->connect(1);
            $first->app->websockets()->message(1, '{"event":"who"}');
            $second->app->websockets()->message(1, '{"event":"who"}');
            expect($first->transport->messages(1)[0]['event'])->toBe('first');
            expect($second->transport->messages(1)[0]['event'])->toBe('second');
            $first->app->websockets()->disconnect(1);
            expect($first->app->websockets()->connections()->count())->toBe(0);
            expect($second->app->websockets()->connections()->count())->toBe(1);
        } finally {
            $second->app->shutdown();
        }
    },
);

it(
    'validates the protocol version and headers before endpoint authorization',
    function (string $key, string $version, int $status) {
        $f = $this->fixture;
        $called = false;
        $f->endpoint->authorize(function () use (&$called) {
            $called = true;
            return true;
        });
        $f->start();
        $http = new HttpFixture(
            uri: '/ws',
            headers: [
                'upgrade' => 'websocket',
                'connection' => 'Upgrade',
                'sec-websocket-key' => $key,
                'sec-websocket-version' => $version,
            ],
        );
        $http->nativeRequest->fd = 1;
        expect(
            (new WebSocketHandshakeListener($f->app))->handle(
                $http->nativeRequest,
                $http->nativeResponse,
            ),
        )->toBeFalse();
        expect($http->nativeResponse->sentStatus)
            ->toBe($status)
            ->and($called)
            ->toBeFalse();
        if ($status === 426) {
            expect(
                $http->nativeResponse->sentHeaders['sec-websocket-version'],
            )->toBe('13');
        }
    },
)->with([
    ['not-base64', '13', 400],
    [base64_encode(str_repeat('x', 16)), '8', 426],
]);

it(
    'cleans failed open hooks and preserves every cleanup failure without retaining state',
    function () {
        $f = $this->fixture;
        $trace = [];
        $f->endpoint->onOpen(function (Connection $connection) use (&$trace) {
            $connection->set('resource', new stdClass());
            $connection->defer(function () use (&$trace) {
                $trace[] = 'cleanup';
                throw new RuntimeException('first failure');
            });
            throw new RuntimeException('open failure');
        });
        $f->endpoint->onClose(function () use (&$trace) {
            $trace[] = 'closed';
            throw new RuntimeException('second failure');
        });
        $f->start();
        $connection = $f->connect(1);
        expect($connection->isClosed())
            ->toBeTrue()
            ->and($connection->request())
            ->toBeNull();
        expect($trace)
            ->toBe(['cleanup', 'closed'])
            ->and($connection->get('resource'))
            ->toBeNull();
        expect($f->app->workers()->activeConnections())->toBe(0);
        expect($f->transport->closed[1][0])->toBe(1011);
    },
);

it(
    'does not broadcast into a pending handshake or discard its connection',
    function () {
        $f = $this->fixture;
        $opened = 0;
        $f->endpoint->onOpen(function () use (&$opened) {
            $opened++;
        });
        $f->start();
        $http = new HttpFixture(uri: '/ws');
        $http->request->fd = 1;
        $connection = $f->app->websockets()->connect($http->request);
        $f->endpoint->broadcast('before-upgrade');
        expect($f->app->websockets()->connections()->count())->toBe(1);
        expect($f->transport->messages(1))
            ->toBe([])
            ->and($connection->isClosed())
            ->toBeFalse();
        $f->transport->open[1] = true;
        $f->app->websockets()->open(1);
        $f->app->websockets()->open(1);
        $f->endpoint->broadcast('after-upgrade');
        expect($opened)
            ->toBe(1)
            ->and($f->transport->messages(1)[0]['event'])
            ->toBe('after-upgrade');
    },
);

it(
    'rejects dispatch modes that cannot preserve connection ownership before boot',
    function () {
        $app = new Application(['server' => ['dispatch_mode' => 1]]);
        $app->websocket('/ws');
        expect(
            fn() => $app->run(new \Oktaax\Runtime\Swoole\ProcessRuntime()),
        )->toThrow(InvalidArgumentException::class, 'dispatch_mode=2');
        expect($app->state())
            ->toBe('configuring')
            ->and($app->nativeServer())
            ->toBeNull();
    },
);
