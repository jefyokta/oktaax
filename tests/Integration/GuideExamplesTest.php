<?php

use Tests\Support\RuntimeServer;

it(
    'runs the documented HTTP recipes with their real entrypoint factories',
    function (string $name, string $path, int $status, string $expected) {
        $server = new RuntimeServer('process', $name);
        try {
            $server->start();
            [$actual, $headers, $body] = $server->request($path);
            expect($actual)->toBe($status)->and($body)->toContain($expected);
            expect($server->logs())->not->toContain(
                'Fatal error',
                'PHP Warning',
                'PHP Deprecated',
            );
        } finally {
            $server->stop();
        }
        expect($server->gracefulShutdown)->toBeTrue();
    },
)
    ->with([
        ['http', '/users/42', 200, '"id":"42"'],
        ['responses', '/custom', 201, 'Notebook'],
        ['lifecycle', '/', 200, 'completed_requests'],
        ['sse', '/events', 200, '"tick":3'],
        ['events', '/', 200, 'counter'],
        ['http-client', '/', 200, 'Hello from the local upstream'],
        ['csrf', '/token', 200, 'token'],
        ['errors', '/http/404', 404, 'A little off the map.'],
        ['inertia', '/', 200, 'data-page='],
        ['websocket', '/', 200, 'WebSocket echo'],
    ])
    ->skip(
        getenv('OKTAAX_INTEGRATION') !== '1',
        'Set OKTAAX_INTEGRATION=1 to run example servers.',
    );

it(
    'executes documented validation, responses, and task failure examples',
    function () {
        $server = new RuntimeServer('process', 'tasks');
        try {
            $server->start();
            [$status, $headers, $body] = $server->request(
                '/uppercase',
                'POST',
                '{"text":"hello"}',
                ['Content-Type: application/json'],
            );
            expect($status)
                ->toBe(200)
                ->and(json_decode($body, true))
                ->toBe(['result' => 'HELLO']);
            expect(
                $server->request('/uppercase', 'POST', '{}', [
                    'Content-Type: application/json',
                ])[0],
            )->toBe(422);
            expect(
                $server->request('/queue', 'POST', '{"text":"hello"}', [
                    'Content-Type: application/json',
                ])[0],
            )->toBe(202);
            expect($server->request('/timeout')[0])->toBe(504);
            expect($server->request('/failure')[0])->toBe(502);
            expect($server->logs())->not->toContain(
                'Fatal error',
                'PHP Warning',
            );
        } finally {
            $server->stop();
        }
        expect($server->gracefulShutdown)->toBeTrue();
    },
)->skip(
    getenv('OKTAAX_INTEGRATION') !== '1',
    'Set OKTAAX_INTEGRATION=1 to run example servers.',
);

it(
    'serves the guide through the coroutine runtime and displays debug pages when configured',
    function () {
        $server = new RuntimeServer('coroutine', 'errors', true);
        try {
            $server->start();
            [$status, $headers, $body] = $server->request('/exception');
            expect($status)
                ->toBe(500)
                ->and($headers['content-type'])
                ->toContain('text/html');
            expect($body)->toContain(
                'DEBUG MODE',
                'The demonstration service is unavailable.',
                'Stack trace',
            );
            expect($server->request('/api/conflict')[0])->toBe(409);
        } finally {
            $server->stop();
        }
        expect($server->gracefulShutdown)->toBeTrue();
    },
)->skip(
    getenv('OKTAAX_INTEGRATION') !== '1',
    'Set OKTAAX_INTEGRATION=1 to run example servers.',
);

it(
    'matches the request and response sequences in the example catalog',
    function (string $name) {
        $server = new RuntimeServer('process', $name);
        try {
            $server->start();
            if ($name === 'http') {
                expect($server->request('/health')[0])->toBe(200);
                expect($server->request('/health', 'POST')[0])->toBe(405);
                [$status, $headers, $body] = $server->request(
                    '/users',
                    'POST',
                    '{"name":"Ada","email":"ada@example.com","admin":true}',
                    ['Content-Type: application/json'],
                );
                expect($status)
                    ->toBe(201)
                    ->and(json_decode($body, true))
                    ->toBe(['name' => 'Ada', 'email' => 'ada@example.com']);
                expect($headers)->toHaveKey('x-request-id');
                expect(
                    $server->request('/users', 'POST', '{}', [
                        'Content-Type: application/json',
                    ])[0],
                )->toBe(422);
                expect(
                    $server->request('/users', 'POST', 'invalid', [
                        'Content-Type: application/json',
                    ])[0],
                )->toBe(400);
                expect($server->request('/private')[0])->toBe(401);
                expect(
                    $server->request(
                        '/private',
                        headers: ['X-Demo-Token: demo'],
                    )[0],
                )->toBe(200);
            } elseif ($name === 'responses') {
                expect($server->request('/?name=Ada')[2])->toContain(
                    'Hello, Ada.',
                );
                expect(
                    json_decode($server->request('/envelope')[2], true),
                )->toBe([
                    'message' => 'Created',
                    'error' => [],
                    'data' => ['id' => 1],
                ]);
                [$status, $headers] = $server->request('/redirect');
                expect($status)
                    ->toBe(302)
                    ->and($headers['location'])
                    ->toBe('/json');
                expect(
                    strtolower($server->request('/cookie')[1]['set-cookie']),
                )->toContain('demo=hello', 'httponly');
                expect($server->request('/download')[2])->toStartWith(
                    'Hello from Oktaax.',
                );
                expect($server->request('/stream')[2])->toBe(
                    "first\nsecond\nthird\n",
                );
                expect($server->request('/generator')[2])->toBe(
                    "first\nsecond\n",
                );
            } elseif ($name === 'csrf') {
                $token = json_decode($server->request('/token')[2], true)[
                    'token'
                ];
                $payload = json_encode([
                    '_token' => $token,
                    'message' => 'Hello',
                ]);
                [$status, $headers, $body] = $server->request(
                    '/message',
                    'POST',
                    $payload,
                    ['Content-Type: application/json'],
                );
                expect($status)
                    ->toBe(200)
                    ->and(json_decode($body, true))
                    ->toBe(['message' => 'Hello']);
                expect(
                    $server->request(
                        '/message',
                        'POST',
                        '{"message":"Hello"}',
                        ['Content-Type: application/json'],
                    )[0],
                )->toBe(403);
            } elseif ($name === 'inertia') {
                [$status, $headers, $body] = $server->request(
                    '/?name=Ada',
                    headers: ['X-Inertia: true'],
                );
                expect($status)
                    ->toBe(200)
                    ->and($headers)
                    ->toHaveKey('x-inertia');
                expect(json_decode($body, true)['props'])->toBe([
                    'name' => 'Ada',
                ]);
            } elseif ($name === 'events') {
                expect($server->request('/increment', 'POST')[0])->toBe(200);
                $counter = 0;
                for (
                    $attempt = 0;
                    $attempt < 10 && $counter === 0;
                    $attempt++
                ) {
                    $counter = json_decode($server->request('/')[2], true)[
                        'counter'
                    ];
                    if ($counter === 0) {
                        usleep(10000);
                    }
                }
                expect($counter)->toBe(1);
            }
            expect($server->logs())->not->toContain(
                'Fatal error',
                'PHP Warning',
                'PHP Deprecated',
            );
        } finally {
            $server->stop();
        }
        expect($server->gracefulShutdown)->toBeTrue();
    },
)
    ->with(['http', 'responses', 'csrf', 'inertia', 'events'])
    ->skip(
        getenv('OKTAAX_INTEGRATION') !== '1',
        'Set OKTAAX_INTEGRATION=1 to run example servers.',
    );

it(
    'exchanges the documented packet with the guide WebSocket echo server',
    function () {
        $server = new RuntimeServer('process', 'websocket');
        $socket = null;
        try {
            $server->start();
            $socket = stream_socket_client(
                'tcp://127.0.0.1:' . $server->port,
                $error,
                $message,
                5,
            );
            stream_set_timeout($socket, 5);
            $key = base64_encode(random_bytes(16));
            fwrite(
                $socket,
                "GET /socket HTTP/1.1\r\nHost: 127.0.0.1\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: {$key}\r\nSec-WebSocket-Version: 13\r\n\r\n",
            );
            $headers = '';
            while (!str_contains($headers, "\r\n\r\n")) {
                $chunk = fread($socket, 1);
                if ($chunk === '') {
                    throw new RuntimeException('Handshake failed');
                }
                $headers .= $chunk;
            }
            expect($headers)->toContain('101 Switching Protocols');
            $payload = '{"event":"echo","data":"Hello"}';
            $mask = random_bytes(4);
            $masked = '';
            for ($index = 0; $index < strlen($payload); $index++) {
                $masked .= $payload[$index] ^ $mask[$index % 4];
            }
            fwrite(
                $socket,
                chr(0x81) . chr(0x80 | strlen($payload)) . $mask . $masked,
            );
            $header = fread($socket, 2);
            expect(strlen($header))->toBe(2);
            $length = ord($header[1]) & 0x7f;
            $body = '';
            while (strlen($body) < $length) {
                $chunk = fread($socket, $length - strlen($body));
                if ($chunk === '') {
                    throw new RuntimeException('Truncated reply');
                }
                $body .= $chunk;
            }
            expect(json_decode($body, true))->toBe([
                'event' => 'echo',
                'data' => 'Hello',
            ]);
        } finally {
            if (is_resource($socket)) {
                fclose($socket);
            }
            $server->stop();
        }
        expect($server->gracefulShutdown)->toBeTrue();
    },
)->skip(
    getenv('OKTAAX_INTEGRATION') !== '1',
    'Set OKTAAX_INTEGRATION=1 to run example servers.',
);
