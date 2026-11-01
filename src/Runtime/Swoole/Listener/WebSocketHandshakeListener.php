<?php

namespace Oktaax\Runtime\Swoole\Listener;

use Oktaax\Application;
use Oktaax\Exception\HttpException;
use Oktaax\Http\Request;
use Oktaax\Websocket\Connection;
use Swoole\Event;
use Swoole\Http\Request as NativeRequest;
use Swoole\Http\Response as NativeResponse;

final class WebSocketHandshakeListener
{
    public function __construct(private Application $application) {}

    public function handle(
        NativeRequest $nativeRequest,
        NativeResponse $response,
    ): bool {
        return $this->application->within(function () use (
            $nativeRequest,
            $response,
        ) {
            $request = new Request($nativeRequest);
            $connection = null;
            try {
                $key = $request->header('sec-websocket-key');
                if (
                    $request->getMethod() !== 'GET' ||
                    !is_string($key) ||
                    strlen(base64_decode($key, true) ?: '') !== 16 ||
                    strtolower((string) $request->header('upgrade')) !==
                        'websocket' ||
                    !in_array(
                        'upgrade',
                        array_map(
                            'trim',
                            explode(
                                ',',
                                strtolower(
                                    (string) $request->header('connection'),
                                ),
                            ),
                        ),
                        true,
                    )
                ) {
                    throw new HttpException(400);
                }
                if ($request->header('sec-websocket-version') !== '13') {
                    throw new HttpException(
                        426,
                        headers: ['Sec-WebSocket-Version' => '13'],
                    );
                }
                $connection = $this->application
                    ->websockets()
                    ->connect($request);
                $response->status(101);
                $response->header('Upgrade', 'websocket');
                $response->header('Connection', 'Upgrade');
                $response->header(
                    'Sec-WebSocket-Accept',
                    base64_encode(
                        sha1(
                            $key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11',
                            true,
                        ),
                    ),
                );
                if (!$response->end()) {
                    $this->application
                        ->websockets()
                        ->disconnect($connection->id());
                    return false;
                }
                // A custom Swoole handshake owns Open dispatch; wait until the native upgrade is active.
                Event::defer(
                    fn() => $this->application
                        ->websockets()
                        ->open($connection->id()),
                );
                return true;
            } catch (\Throwable $error) {
                if ($connection !== null) {
                    $connection->close(1011, 'Handshake failed');
                }
                if (!$response->isWritable()) {
                    error_log((string) $error);
                    return false;
                }
                $status =
                    $error instanceof HttpException
                        ? $error->getStatusCode()
                        : 500;
                if (!($error instanceof HttpException)) {
                    error_log((string) $error);
                }
                $response->status($status);
                if ($error instanceof HttpException) {
                    foreach ($error->getHeaders() as $name => $value) {
                        $response->header($name, $value);
                    }
                }
                $response->header('content-type', 'application/json');
                $response->end(
                    json_encode(
                        [
                            'error' => 'WebSocket handshake rejected',
                            'status' => $status,
                        ],
                        JSON_THROW_ON_ERROR,
                    ),
                );
                return false;
            } finally {
                try {
                    $request->scope()->close();
                } catch (\Throwable $error) {
                    error_log((string) $error);
                    $connection?->close(1011, 'Handshake cleanup failed');
                }
            }
        });
    }
}
