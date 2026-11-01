<?php

use Tests\Support\RuntimeServer;

it(
    'serves HTTP through each runtime with the same kernel and startup contracts',
    function ($mode) {
        $server = new RuntimeServer($mode);
        try {
            $server->start();
            [$status, $headers, $body] = $server->request(
                '/users/42?extra=yes',
            );
            expect($status)
                ->toBe(200)
                ->and(json_decode($body, true))
                ->toBe(['id' => '42', 'app' => 'runtime-' . $mode]);
            [$status, $headers] = $server->request('/users/42', 'POST');
            expect($status)->toBe(405)->and($headers['allow'])->toBe('GET');
            expect($server->request('/unknown')[0])->toBe(404);
            expect(
                $server->request('/echo', 'POST', 'garbage {"ok":true}', [
                    'Content-Type: application/json',
                ])[0],
            )->toBe(400);
            [$status, $headers, $body] = $server->request(
                '/validated',
                'POST',
                '{"name":"Ada","admin":true}',
                ['Content-Type: application/json'],
            );
            expect($status)
                ->toBe(200)
                ->and(json_decode($body, true))
                ->toBe(['name' => 'Ada']);
            [$status, $headers, $body] = $server->request('/stream');
            expect($status)
                ->toBe(202)
                ->and($headers['content-type'])
                ->toBe('text/plain')
                ->and($body)
                ->toBe('firstsecond');
            [$status, $headers, $body] = $server->request('/file');
            expect($status)
                ->toBe(206)
                ->and($headers['x-file'])
                ->toBe('yes')
                ->and($body)
                ->toStartWith('<?php');
            for ($i = 0; $i < 2; $i++) {
                expect($server->request('/mime')[1]['content-type'])->toBe(
                    'application/json',
                );
            }
            [$status, $headers, $body] = $server->request('/sse');
            expect($status)
                ->toBe(200)
                ->and($headers['content-type'])
                ->toContain('text/event-stream')
                ->and($body)
                ->toContain('data: {"connected":true}');
            expect($server->request('/users/42', 'HEAD')[2])->toBe('');
            [$status, $headers, $body] = $server->request('/startup');
            expect($status)
                ->toBe(200)
                ->and(json_decode($body, true)['native'])
                ->toBe(
                    $mode === 'process'
                        ? Swoole\Http\Server::class
                        : Swoole\Coroutine\Http\Server::class,
                );
            if ($mode === 'process') {
                expect(json_decode($server->request('/task')[2], true))->toBe([
                    'result' => 'completed',
                ]);
                expect(
                    json_decode($server->request('/task-timeout')[2], true),
                )->toBe(['timeout' => true, 'pending' => 0]);
                expect(
                    json_decode($server->request('/task-failure')[2], true),
                )->toBe(['error' => DomainException::class, 'pending' => 0]);
                expect(
                    json_decode($server->request('/pending')[2], true),
                )->toBe(['pending' => 0]);
            }
            expect($server->logs())->not->toContain(
                'Fatal error',
                'PHP Warning',
            );
        } finally {
            $server->stop();
        }
        expect($server->gracefulShutdown)->toBeTrue();
    },
)
    ->with(['process', 'coroutine'])
    ->skip(
        getenv('OKTAAX_INTEGRATION') !== '1',
        'Set OKTAAX_INTEGRATION=1 to run local socket integration tests.',
    );

it(
    'composes WebSocket endpoints with the HTTP application context',
    function () {
        $server = new RuntimeServer('websocket');
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
                    throw new RuntimeException(
                        'WebSocket handshake failed: ' . $server->logs(),
                    );
                }
                $headers .= $chunk;
            }
            expect($headers)->toContain('101 Switching Protocols');
            $payload = '{"event":"echo","data":"hello"}';
            $mask = random_bytes(4);
            $masked = '';
            for ($i = 0; $i < strlen($payload); $i++) {
                $masked .= $payload[$i] ^ $mask[$i % 4];
            }
            fwrite(
                $socket,
                chr(0x81) . chr(0x80 | strlen($payload)) . $mask . $masked,
            );
            $frame = fread($socket, 2);
            expect(strlen($frame))->toBe(2);
            $length = ord($frame[1]) & 0x7f;
            $body = '';
            while (strlen($body) < $length) {
                $chunk = fread($socket, $length - strlen($body));
                if ($chunk === '') {
                    throw new RuntimeException(
                        'WebSocket response was truncated.',
                    );
                }
                $body .= $chunk;
            }
            expect(json_decode($body, true))->toBe([
                'event' => 'echo',
                'data' => ['app' => 'runtime-websocket', 'event' => 'echo'],
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
    'Set OKTAAX_INTEGRATION=1 to run local socket integration tests.',
);
