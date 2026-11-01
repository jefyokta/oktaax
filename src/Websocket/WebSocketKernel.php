<?php

namespace Oktaax\Websocket;

use Oktaax\Application;
use Oktaax\Exception\CleanupException;
use Oktaax\Exception\HttpException;
use Oktaax\Exception\ValidationException;
use Oktaax\Http\Request;
use Oktaax\Validation\Validator;
use Throwable;

final class WebSocketKernel
{
    /**
     * Frozen endpoint definitions owned by this application.
     *
     * @var array<string, \Oktaax\Websocket\Endpoint>
     */
    private array $endpoints = [];
    private ConnectionRegistry $connections;
    private ?Transport $transport = null;

    public function __construct(private Application $application)
    {
        $this->connections = new ConnectionRegistry();
    }
    public function endpoint(string $path): Endpoint
    {
        $this->application->assertConfigurable();
        if (
            !str_starts_with($path, '/') ||
            str_starts_with($path, '//') ||
            strpbrk($path, "?#\r\n\0") !== false
        ) {
            throw new \InvalidArgumentException(
                'WebSocket endpoint must be an absolute path without query or fragment.',
            );
        }
        return $this->endpoints[$path] ??= new Endpoint($path, $this);
    }
    public function hasEndpoints(): bool
    {
        return $this->endpoints !== [];
    }
    public function freeze(): void
    {
        foreach ($this->endpoints as $endpoint) {
            $endpoint->freeze();
        }
    }
    public function connections(): ConnectionRegistry
    {
        return $this->connections;
    }
    public function attachTransport(Transport $transport): void
    {
        if ($this->connections->count() > 0) {
            throw new \LogicException(
                'Cannot replace transport with active connections.',
            );
        }
        $this->transport = $transport;
    }
    /**
     * Authorize a handshake and register a pending connection without firing open hooks.
     *
     * @internal Called by a runtime handshake adapter.
     * @throws \Oktaax\Exception\HttpException For unknown endpoints, denied authorization, or stopping workers.
     * @throws \LogicException When no transport or descriptor is available.
     */
    public function connect(Request $request): Connection
    {
        return $this->application->within(function () use ($request) {
            if (!$this->application->workers()->isRunning()) {
                throw new HttpException(503);
            }
            $endpoint =
                $this->endpoints[$request->path()] ??
                throw new HttpException(404);
            if (!$endpoint->allows($request)) {
                throw new HttpException(403);
            }
            if (!$this->application->workers()->isRunning()) {
                throw new HttpException(503);
            }
            $transport =
                $this->transport ??
                throw new \LogicException(
                    'WebSocket transport is not attached.',
                );
            $fd = $request->fd;
            if (!is_int($fd)) {
                throw new \LogicException(
                    'Handshake request has no connection descriptor.',
                );
            }
            $connection = new Connection(
                $fd,
                $endpoint,
                $request,
                $this->application,
                $transport,
                fn() => $this->disconnect($fd),
            );
            $this->connections->add($connection);
            return $connection;
        });
    }
    public function open(int $fd): void
    {
        $this->application->within(function () use ($fd) {
            $connection = $this->connections->get($fd);
            if ($connection === null) {
                return;
            }
            if (!$connection->isOpen()) {
                $this->disconnect($fd);
                return;
            }
            if (!$connection->activate()) {
                return;
            }
            try {
                $connection->endpoint()->opened($connection);
            } catch (Throwable $error) {
                try {
                    $this->report($error, $connection);
                } finally {
                    $connection->close(
                        1011,
                        'Connection initialization failed',
                    );
                }
            }
        });
    }
    /**
     * Decode, dispatch, report errors, and release message resources within the owning application scope.
     *
     * @internal Called by the native message listener.
     */
    public function message(
        int $fd,
        string $payload,
        int $opcode = SWOOLE_WEBSOCKET_OPCODE_TEXT,
    ): void {
        $this->application->within(function () use ($fd, $payload, $opcode) {
            $this->open($fd);
            $connection = $this->connections->get($fd);
            if ($connection === null || $connection->isClosed()) {
                return;
            }
            $message = null;
            try {
                $endpoint = $connection->endpoint();
                if (strlen($payload) > $endpoint->messageLimit()) {
                    throw new ProtocolException(
                        'Message exceeds the endpoint limit.',
                        1009,
                    );
                }
                $message = $endpoint->messageCodec()->decode($payload, $opcode);
                $message->useValidator(
                    $this->application->container(Validator::class),
                );
                if (!$endpoint->dispatch($connection, $message)) {
                    $connection->send(
                        'error',
                        [
                            'code' => 'unknown_event',
                            'message' =>
                                'No handler is registered for this event.',
                        ],
                        $message->id(),
                    );
                }
            } catch (Throwable $error) {
                $this->report($error, $connection, $message);
                if ($error instanceof ProtocolException) {
                    $connection->close($error->closeCode, 'Invalid message');
                }
            } finally {
                try {
                    $message?->finish();
                } catch (Throwable $error) {
                    $this->report($error, $connection, $message);
                }
            }
        });
    }
    public function report(
        Throwable $error,
        Connection $connection,
        ?Message $message = null,
    ): void {
        $this->application->within(function () use (
            $error,
            $connection,
            $message,
        ) {
            try {
                if (
                    $connection
                        ->endpoint()
                        ->handleError($error, $connection, $message)
                ) {
                    return;
                }
                if ($error instanceof ValidationException) {
                    $data = [
                        'code' => 'validation_failed',
                        'message' => 'Message validation failed.',
                        'errors' => $error->getErrors(),
                    ];
                } elseif ($error instanceof ProtocolException) {
                    $data = [
                        'code' => 'invalid_message',
                        'message' => $error->getMessage(),
                    ];
                } else {
                    error_log((string) $error);
                    $data = [
                        'code' => 'internal_error',
                        'message' => 'Unable to process this message.',
                    ];
                }
                $connection->send('error', $data, $message?->id());
            } catch (Throwable $failure) {
                error_log((string) $failure);
                $connection->close(1011, 'Message processing failed');
            }
        });
    }
    public function disconnect(int $fd): void
    {
        $connection = $this->connections->remove($fd);
        if ($connection !== null) {
            try {
                $connection->finish();
            } catch (Throwable $error) {
                error_log((string) $error);
            }
        }
    }
    public function drain(): void
    {
        CleanupException::run(
            array_map(
                fn(Connection $connection) => fn() => $connection->close(
                    1001,
                    'Server is stopping',
                ),
                $this->connections->all(),
            ),
        );
    }
    /**
     * Encode once, deliver locally, and forward serializable data to peer HTTP workers without awaiting delivery.
     *
     * @throws \LogicException When no active worker or transport is available.
     */
    public function broadcast(
        Endpoint $endpoint,
        Message $message,
        ?string $room = null,
        ?int $except = null,
    ): void {
        if (
            !$this->application->workers()->isRunning() ||
            $this->transport === null
        ) {
            throw new \LogicException(
                'WebSocket broadcast requires an active worker and transport.',
            );
        }
        $codec = $endpoint->messageCodec();
        $broadcast = new Broadcast(
            $endpoint->path(),
            $codec->encode($message),
            $codec->opcode(),
            $room,
            $except,
            $except === null ? null : $this->application->worker->id,
        );
        $this->receive($broadcast);
        if ($this->application->config('server.worker_num', 1) > 1) {
            $this->application->worker->send($broadcast);
        }
    }
    /**
     * Deliver an IPC broadcast only to activated matching endpoint/room connections; never rebroadcast it.
     *
     * @internal
     */
    public function receive(Broadcast $broadcast): void
    {
        $this->application->within(function () use ($broadcast) {
            foreach ($this->connections->all() as $connection) {
                if (
                    !$connection->isActivated() ||
                    $connection->endpoint()->path() !== $broadcast->path ||
                    ($connection->id() === $broadcast->except &&
                        $this->application->worker?->id ===
                            $broadcast->exceptWorker) ||
                    ($broadcast->room !== null &&
                        !$connection->inRoom($broadcast->room))
                ) {
                    continue;
                }
                $connection->sendEncoded(
                    $broadcast->payload,
                    $broadcast->opcode,
                );
            }
        });
    }
}
