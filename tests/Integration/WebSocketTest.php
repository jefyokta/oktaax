<?php

use Tests\Support\RuntimeServer;
use Tests\Support\WebSocketPeer;

function authenticatedPeer(
    RuntimeServer $server,
    string $path = '/ws',
): WebSocketPeer {
    return new WebSocketPeer($server->port, $path, [
        'Origin: https://allowed.test',
        'Cookie: auth=demo',
    ]);
}

it(
    'routes and authorizes before upgrade while preserving ordinary HTTP',
    function () {
        $server = new RuntimeServer(
            'single',
            entrypoint: 'websocket-server.php',
        );
        $peers = [];
        try {
            $server->start();
            expect(
                json_decode($server->request('/native')[2], true)['class'],
            )->toBe(Swoole\WebSocket\Server::class);
            foreach (
                [
                    ['/missing', [], 404],
                    ['/ws', [], 403],
                    [
                        '/ws',
                        ['Origin: https://evil.test', 'Cookie: auth=demo'],
                        403,
                    ],
                    ['/ws', ['Origin: https://allowed.test'], 403],
                ]
                as [$path, $headers, $status]
            ) {
                $peer = new WebSocketPeer($server->port, $path, $headers);
                $peers[] = $peer;
                expect($peer->status)->toBe($status);
                $peer->close();
            }
            $badKey = new WebSocketPeer($server->port, key: 'invalid');
            $peers[] = $badKey;
            expect($badKey->status)->toBe(400);
            $badKey->close();
            $badVersion = new WebSocketPeer($server->port, version: '8');
            $peers[] = $badVersion;
            expect($badVersion->status)->toBe(426);
            expect($badVersion->headers['sec-websocket-version'])->toBe('13');
            $badVersion->close();
            expect(
                json_decode($server->request('/state')[2], true)['opened'],
            )->toBe(0);
            $peer = authenticatedPeer($server);
            $peers[] = $peer;
            expect($peer->status)->toBe(101);
            $ready = $peer->receive();
            expect($ready['event'])
                ->toBe('ready')
                ->and($ready['data']['request_is_null'])
                ->toBeTrue();
            $peer->send('echo', 'one', 'id-1');
            expect($peer->receive())->toBe([
                'event' => 'echo',
                'data' => ['payload' => 'one', 'sequence' => 1],
                'id' => 'id-1',
            ]);
            $peer->send('echo', 'two');
            expect($peer->receive()['data']['sequence'])->toBe(2);
            $peer->close();
            for ($attempt = 0; $attempt < 30; $attempt++) {
                $state = json_decode($server->request('/state')[2], true);
                if ($state['connections'] === 0) {
                    break;
                }
                usleep(10000);
            }
            expect($state)->toBe([
                'opened' => 1,
                'closed' => 1,
                'cleaned' => 1,
                'messages' => 2,
                'connections' => 0,
            ]);
            expect($server->logs())->not->toContain(
                'PHP Warning',
                'Fatal error',
            );
        } finally {
            foreach ($peers as $peer) {
                $peer->close();
            }
            $server->stop();
        }
        expect($server->gracefulShutdown)->toBeTrue();
    },
)->skip(
    getenv('OKTAAX_INTEGRATION') !== '1',
    'Set OKTAAX_INTEGRATION=1 to test WebSocket sockets.',
);

it(
    'sends structured errors, supports timers, and keeps recoverable errors connected',
    function () {
        $server = new RuntimeServer(
            'single',
            entrypoint: 'websocket-server.php',
        );
        $peer = null;
        try {
            $server->start();
            $peer = authenticatedPeer($server);
            $peer->receive();
            $peer->send('unknown', null, 'missing');
            expect($peer->receive()['data']['code'])->toBe('unknown_event');
            $peer->send('validate', [], 'validation');
            $error = $peer->receive();
            expect($error['data']['code'])
                ->toBe('validation_failed')
                ->and($error['id'])
                ->toBe('validation');
            $peer->send('validate', ['text' => 'kept', 'admin' => true]);
            expect($peer->receive())->toBe([
                'event' => 'valid',
                'data' => ['text' => 'kept'],
            ]);
            $peer->send('fail');
            $error = $peer->receive();
            expect($error['data']['code'])->toBe('internal_error');
            expect(json_encode($error))->not->toContain('private diagnostic');
            $peer->send('later');
            expect($peer->receive())->toBe([
                'event' => 'later',
                'data' => ['app' => 'ws-test'],
            ]);
            $peer->send('bye');
            [$opcode, $payload] = $peer->frame();
            expect($opcode)
                ->toBe(8)
                ->and(unpack('n', substr($payload, 0, 2))[1])
                ->toBe(1000);
        } finally {
            $peer?->close();
            $server->stop();
        }
        expect($server->gracefulShutdown)->toBeTrue();
    },
)->skip(
    getenv('OKTAAX_INTEGRATION') !== '1',
    'Set OKTAAX_INTEGRATION=1 to test WebSocket sockets.',
);

it('rejects bad payloads with protocol close codes', function (
    string $payload,
    int $opcode,
    int $code,
) {
    $server = new RuntimeServer('single', entrypoint: 'websocket-server.php');
    $peer = null;
    try {
        $server->start();
        $peer = authenticatedPeer($server);
        $peer->receive();
        $peer->raw($payload, $opcode);
        expect($peer->receive()['data']['code'])->toBe('invalid_message');
        [$actual, $body] = $peer->frame();
        expect($actual)
            ->toBe(8)
            ->and(unpack('n', substr($body, 0, 2))[1])
            ->toBe($code);
        expect($server->logs())->not->toContain('PHP Warning', 'Fatal error');
    } finally {
        $peer?->close();
        $server->stop();
    }
    expect($server->gracefulShutdown)->toBeTrue();
})
    ->with([
        ['not-json', 1, 1007],
        ['{"event":[]}', 1, 1008],
        ['binary', 2, 1003],
        [str_repeat('x', 257), 1, 1009],
    ])
    ->skip(
        getenv('OKTAAX_INTEGRATION') !== '1',
        'Set OKTAAX_INTEGRATION=1 to test WebSocket sockets.',
    );

it(
    'broadcasts room and endpoint messages across HTTP workers and drains live connections',
    function () {
        $server = new RuntimeServer(
            'multi',
            entrypoint: 'websocket-server.php',
        );
        $peers = [];
        $workers = [];
        try {
            $server->start();
            for (
                $attempt = 0;
                $attempt < 8 && count($workers) < 2;
                $attempt++
            ) {
                $peer = authenticatedPeer($server);
                $peers[] = $peer;
                $ready = $peer->receive();
                $workers[$ready['data']['worker']] = $peer;
            }
            expect(count($workers))->toBe(2);
            $members = array_values($workers);
            foreach ($members as $peer) {
                $peer->send('join', ['room' => 'team']);
                expect($peer->receive()['event'])->toBe('joined');
            }
            $members[0]->send('publish', [
                'room' => 'team',
                'text' => 'cross-worker',
            ]);
            foreach ($members as $peer) {
                expect($peer->receive())->toBe([
                    'event' => 'news',
                    'data' => ['text' => 'cross-worker'],
                ]);
            }
            $public = new WebSocketPeer($server->port, '/public');
            $peers[] = $public;
            $members[0]->send('all');
            foreach ($members as $peer) {
                expect($peer->receive()['event'])->toBe('all');
            }
            expect($public->hasData())->toBeFalse();
            // Stop with accepted sockets and recurring connection timers still alive.
            $server->stop();
            preg_match_all(
                '/WS_CLEANUP (\{[^\n]+\})/',
                $server->logs(),
                $matches,
            );
            expect(count($matches[1]))->toBe(2);
            foreach ($matches[1] as $json) {
                $state = json_decode($json, true);
                expect($state['closed'])->toBe($state['opened']);
                expect($state['cleaned'])
                    ->toBe($state['opened'])
                    ->and($state['active'])
                    ->toBe(0);
            }
        } finally {
            foreach ($peers as $peer) {
                $peer->close();
            }
            $server->stop();
        }
        expect($server->gracefulShutdown)->toBeTrue();
    },
)->skip(
    getenv('OKTAAX_INTEGRATION') !== '1',
    'Set OKTAAX_INTEGRATION=1 to test WebSocket sockets.',
);

it(
    'selects the WebSocket server automatically through the fluent listen entrypoint',
    function () {
        $server = new RuntimeServer(
            'fluent',
            entrypoint: 'websocket-server.php',
        );
        $peer = null;
        try {
            $server->start();
            $peer = authenticatedPeer($server);
            expect($peer->status)
                ->toBe(101)
                ->and($peer->receive()['event'])
                ->toBe('ready');
            $peer->send('echo', 'fluent');
            expect($peer->receive()['data']['payload'])->toBe('fluent');
            expect($server->logs())->not->toContain(
                'PHP Warning',
                'Fatal error',
            );
        } finally {
            $peer?->close();
            $server->stop();
        }
        expect($server->gracefulShutdown)->toBeTrue();
    },
)->skip(
    getenv('OKTAAX_INTEGRATION') !== '1',
    'Set OKTAAX_INTEGRATION=1 to test WebSocket sockets.',
);
