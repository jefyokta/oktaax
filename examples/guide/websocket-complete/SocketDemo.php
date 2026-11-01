<?php

namespace OktaaxExamples\WebSocket;

use Oktaax\Application;
use Oktaax\Event\Event;
use Oktaax\Event\EventEmitter;
use Oktaax\Exception\ValidationException;
use Oktaax\Http\Request;
use Oktaax\Websocket\Connection;
use Oktaax\Websocket\Endpoint;
use Oktaax\Websocket\Message;
use Oktaax\Websocket\ProtocolException;
use Throwable;

final class SocketDemo
{
    public static function register(
        Application $app,
        DemoAuth $auth,
        bool $logMessages,
    ): void {
        $chat = $app->websocket('/socket/chat');
        self::protect($chat, $auth, $logMessages);
        $chat->onOpen(function (Connection $connection) use (
            $app,
            $auth,
            $chat,
        ): void {
            $identity = self::open($connection, $auth);
            $connection->join('lobby');

            // This public emitter is a backend source, separate from the endpoint's private emitter.
            $events = self::events($app);
            $listener = static function (Event $event) use ($connection): void {
                $connection->send('backend.notice', $event->data);
            };
            $events->on('demo.notice', $listener);
            $connection->defer(
                static fn() => $events->off('demo.notice', $listener),
            );

            if (!self::welcome($connection, $app, $identity)) {
                return;
            }
            $chat
                ->target()
                ->except($connection)
                ->send('presence.joined', ['user' => $identity->name]);
            $connection->tick(15000, static function (
                Connection $connection,
            ): void {
                if (microtime(true) - $connection->get('last_pong_at') > 45) {
                    $connection->close(4408, 'Heartbeat timeout');
                    return;
                }
                $nonce = bin2hex(random_bytes(8));
                $connection->set('heartbeat_nonce', $nonce);
                $connection->send('heartbeat.ping', ['nonce' => $nonce]);
            });
        });
        $chat->onClose(static function (Connection $connection) use (
            $chat,
            $app,
        ): void {
            $identity = $connection->get('identity');
            if ($identity instanceof Identity && $app->workers()->isRunning()) {
                $chat->broadcast('presence.left', ['user' => $identity->name]);
            }
        });
        $chat->on('heartbeat.pong', static function (
            Connection $connection,
            Message $message,
        ): void {
            $data = $message->validate(['nonce' => 'required|string']);
            $expected = $connection->get('heartbeat_nonce');
            if (
                !is_string($expected) ||
                !hash_equals($expected, $data['nonce'])
            ) {
                throw new ClientProblem(
                    'invalid_heartbeat',
                    'Heartbeat nonce is no longer valid.',
                );
            }
            $connection->set('last_pong_at', microtime(true));
        });
        self::basicHandlers($chat);
        self::roomHandlers($chat);
        self::timerHandlers($chat);

        $chat->on('admin.announce', static function (
            Connection $connection,
            Message $message,
        ) use ($chat): void {
            if (self::user($connection)->role !== 'admin') {
                throw new ClientProblem(
                    'forbidden',
                    'Only an admin can announce.',
                );
            }
            $chat->broadcast('announcement', [
                'text' => self::text($message),
                'by' => self::user($connection)->name,
            ]);
            $connection->send(
                'admin.announced',
                ['accepted' => true],
                $message->id(),
            );
        });
        $chat->on('document.preview', static function (
            Connection $connection,
            Message $message,
        ): void {
            $text = self::text($message, 8192);
            $stream = fopen('php://temp', 'w+');
            if ($stream === false) {
                throw new \RuntimeException('Cannot create a preview stream.');
            }
            // Message resources end here, while the WebSocket connection remains open.
            $message->defer(static fn() => fclose($stream));
            fwrite($stream, $text);
            rewind($stream);
            $hash = hash_init('sha256');
            $bytes = hash_update_stream($hash, $stream);
            $connection->send(
                'document.previewed',
                ['bytes' => $bytes, 'sha256' => hash_final($hash)],
                $message->id(),
            );
        });

        // A second exact path has its own permissions, handlers, and local emitter.
        $status = $app->websocket('/socket/status');
        self::protect($status, $auth, $logMessages, true);
        $status->onOpen(static function (Connection $connection) use (
            $auth,
            $app,
        ): void {
            $identity = self::open($connection, $auth);
            if (!self::welcome($connection, $app, $identity)) {
                return;
            }
            $connection->tick(2000, static function (
                Connection $connection,
            ) use ($app): void {
                $connection->send('status.tick', [
                    'worker' => $app->worker?->name,
                    'local_connections' => $app
                        ->websockets()
                        ->connections()
                        ->count(),
                    'at' => gmdate(DATE_ATOM),
                ]);
            });
        });
        self::basicHandlers($status);

        $alternate = $app
            ->websocket('/socket/alternate')
            ->codec(new AlternateJsonCodec());
        self::protect($alternate, $auth, $logMessages);
        $alternate->onOpen(static function (Connection $connection) use (
            $auth,
            $app,
        ): void {
            self::welcome($connection, $app, self::open($connection, $auth));
        });
        self::basicHandlers($alternate);

        $binary = $app
            ->websocket('/socket/binary')
            ->codec(new BinaryJsonCodec());
        self::protect($binary, $auth, $logMessages);
        $binary->onOpen(static function (Connection $connection) use (
            $auth,
            $app,
        ): void {
            self::welcome($connection, $app, self::open($connection, $auth));
        });
        self::basicHandlers($binary);
    }

    private static function protect(
        Endpoint $endpoint,
        DemoAuth $auth,
        bool $logMessages,
        bool $adminOnly = false,
    ): void {
        $endpoint
            ->origins($auth->origins)
            ->maxMessageBytes(16384)
            ->authorize(static function (Request $request) use (
                $auth,
                $adminOnly,
            ): bool {
                $identity = $auth->fromRequest($request);
                return $identity !== null &&
                    (!$adminOnly || $identity->role === 'admin');
            });
        $endpoint->use(static function (
            Connection $connection,
            Message $message,
            callable $next,
        ) {
            $identity = self::user($connection);
            if ($identity->expiresAt <= time()) {
                throw new ClientProblem(
                    'session_expired',
                    'Session expired',
                    4401,
                );
            }
            $now = hrtime(true) / 1e9;
            if ($now - $connection->get('rate_window', 0) >= 10) {
                $connection->set('rate_window', $now)->set('rate_count', 0);
            }
            $count = $connection->get('rate_count', 0) + 1;
            $connection->set('rate_count', $count);
            if ($count > 30) {
                throw new ClientProblem(
                    'rate_limited',
                    'Too many messages; wait ten seconds.',
                    $count > 33 ? 4408 : null,
                );
            }
            $connection->set(
                'handled_messages',
                $connection->get('handled_messages', 0) + 1,
            );
            return $next();
        });
        if ($logMessages) {
            $endpoint->use(static function (
                Connection $connection,
                Message $message,
                callable $next,
            ) {
                $started = hrtime(true);
                $event = $message->event();
                $id = $message->id();
                $peer = $connection->id();
                // Copy metadata rather than keeping a Message in a connection callback.
                $message->defer(
                    static fn() => error_log(
                        json_encode(
                            [
                                'event' => $event,
                                'id' => $id,
                                'connection' => $peer,
                                'duration_ms' => round(
                                    (hrtime(true) - $started) / 1e6,
                                    2,
                                ),
                            ],
                            JSON_THROW_ON_ERROR,
                        ),
                    ),
                );
                return $next();
            });
        }
        $endpoint->onError(static function (
            Throwable $error,
            Connection $connection,
            ?Message $message,
        ): void {
            if ($error instanceof ValidationException) {
                $data = [
                    'code' => 'validation_failed',
                    'message' => 'Message validation failed.',
                    'errors' => $error->getErrors(),
                ];
            } elseif ($error instanceof ClientProblem) {
                $data = [
                    'code' => $error->kind,
                    'message' => $error->getMessage(),
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
            if (
                $connection->send('error', $data, $message?->id()) &&
                $error instanceof ClientProblem &&
                $error->closeCode !== null
            ) {
                $connection->close($error->closeCode, $error->getMessage());
            }
        });
        $endpoint->onClose(static function (Connection $connection): void {
            error_log(
                sprintf(
                    '[ws] closed endpoint=%s connection=%d messages=%d',
                    $connection->endpoint()->path(),
                    $connection->id(),
                    $connection->get('handled_messages', 0),
                ),
            );
        });
    }

    private static function open(
        Connection $connection,
        DemoAuth $auth,
    ): Identity {
        $request = $connection->request();
        $identity = $request === null ? null : $auth->fromRequest($request);
        if ($identity === null) {
            throw new ClientProblem('session_expired', 'Session expired', 4401);
        }
        $connection
            ->set('identity', $identity)
            ->set('last_pong_at', microtime(true));
        $connection->after(
            max(1, ($identity->expiresAt - time()) * 1000),
            static function (Connection $connection): void {
                $connection->send('session.expired', [
                    'message' => 'Sign in again.',
                ]);
                $connection->close(4401, 'Session expired');
            },
        );
        return $identity;
    }

    private static function welcome(
        Connection $connection,
        Application $app,
        Identity $identity,
    ): bool {
        return $connection->send('ready', [
            'connection' => $connection->id(),
            'worker' => $app->worker?->name,
            'endpoint' => $connection->endpoint()->path(),
            'user' => $identity->toArray(),
            'rooms' => $connection->rooms(),
            'heartbeat_seconds' =>
                $connection->endpoint()->path() === '/socket/chat' ? 15 : null,
        ]);
    }

    private static function user(Connection $connection): Identity
    {
        $identity = $connection->get('identity');
        return $identity instanceof Identity
            ? $identity
            : throw new ClientProblem('unauthenticated', 'Sign in again', 4401);
    }

    private static function basicHandlers(Endpoint $endpoint): void
    {
        $endpoint->on(
            'echo',
            static fn(
                Connection $connection,
                Message $message,
            ) => $connection->send('echo', $message->data(), $message->id()),
        );
        $endpoint->on(
            'ping',
            static fn(
                Connection $connection,
                Message $message,
            ) => $connection->send(
                'pong',
                ['at' => gmdate(DATE_ATOM)],
                $message->id(),
            ),
        );
        $endpoint->on(
            'state.get',
            static fn(
                Connection $connection,
                Message $message,
            ) => $connection->send(
                'state',
                [
                    'user' => self::user($connection)->toArray(),
                    'rooms' => $connection->rooms(),
                    'open' => $connection->isOpen(),
                    'handled_messages' => $connection->get(
                        'handled_messages',
                        0,
                    ),
                    'clock_running' => is_int($connection->get('clock_timer')),
                ],
                $message->id(),
            ),
        );
        $endpoint->on('client.close', static function (
            Connection $connection,
            Message $message,
        ): void {
            if ($connection->send('closing', null, $message->id())) {
                $connection->close(1000, 'Client requested closure');
            }
        });
        $endpoint->on(
            'demo.fail',
            static fn() => throw new \RuntimeException(
                'Demonstration internal failure; details stay on the server.',
            ),
        );
    }

    private static function roomHandlers(Endpoint $endpoint): void
    {
        $endpoint->on('chat.join', static function (
            Connection $connection,
            Message $message,
        ): void {
            $room = self::room($connection, $message);
            $connection->join($room);
            $connection->send(
                'chat.joined',
                ['room' => $room, 'rooms' => $connection->rooms()],
                $message->id(),
            );
        });
        $endpoint->on('chat.leave', static function (
            Connection $connection,
            Message $message,
        ): void {
            $room = self::room($connection, $message);
            $connection->leave($room);
            $connection->send(
                'chat.left',
                ['room' => $room, 'rooms' => $connection->rooms()],
                $message->id(),
            );
        });
        $endpoint->on('chat.send', static function (
            Connection $connection,
            Message $message,
        ) use ($endpoint): void {
            $room = self::room($connection, $message, true);
            $text = self::text($message);
            $id = bin2hex(random_bytes(12));
            $endpoint->room($room)->send('chat.message', [
                'message_id' => $id,
                'room' => $room,
                'text' => $text,
                'user' => self::user($connection)->name,
                'at' => gmdate(DATE_ATOM),
            ]);
            // Accepted by this worker; it is not a durable delivery receipt.
            $connection->send(
                'chat.sent',
                ['message_id' => $id, 'room' => $room],
                $message->id(),
            );
        });
        $endpoint->on('chat.typing', static function (
            Connection $connection,
            Message $message,
        ) use ($endpoint): void {
            $room = self::room($connection, $message, true);
            $endpoint
                ->room($room)
                ->except($connection)
                ->send('chat.typing', [
                    'room' => $room,
                    'user' => self::user($connection)->name,
                ]);
            $connection->send(
                'chat.typing.accepted',
                ['room' => $room],
                $message->id(),
            );
        });
    }

    private static function timerHandlers(Endpoint $endpoint): void
    {
        $endpoint->on('clock.start', static function (
            Connection $connection,
            Message $message,
        ): void {
            $data = $message->validate(['milliseconds' => 'integer']);
            $milliseconds = (int) ($data['milliseconds'] ?? 1000);
            if ($milliseconds < 500 || $milliseconds > 10000) {
                throw new ValidationException([
                    'milliseconds' => 'Choose 500–10000 milliseconds.',
                ]);
            }
            self::cancel($connection, 'clock_timer');
            $connection->set('clock_sequence', 0);
            $timer = $connection->tick($milliseconds, static function (
                Connection $connection,
            ): void {
                $sequence = $connection->get('clock_sequence', 0) + 1;
                $connection->set('clock_sequence', $sequence);
                $connection->send('clock.tick', [
                    'sequence' => $sequence,
                    'at' => gmdate(DATE_ATOM),
                ]);
            });
            if ($timer === false) {
                throw new \RuntimeException('Cannot start the clock timer.');
            }
            $connection->set('clock_timer', $timer);
            $connection->send(
                'clock.started',
                ['milliseconds' => $milliseconds],
                $message->id(),
            );
        });
        $endpoint->on('clock.stop', static function (
            Connection $connection,
            Message $message,
        ): void {
            $connection->send(
                'clock.stopped',
                ['cancelled' => self::cancel($connection, 'clock_timer')],
                $message->id(),
            );
        });
        $endpoint->on('reminder.schedule', static function (
            Connection $connection,
            Message $message,
        ): void {
            $text = self::text($message);
            $data = $message->validate(['milliseconds' => 'required|integer']);
            $milliseconds = (int) $data['milliseconds'];
            if ($milliseconds < 100 || $milliseconds > 30000) {
                throw new ValidationException([
                    'milliseconds' => 'Choose 100–30000 milliseconds.',
                ]);
            }
            self::cancel($connection, 'reminder_timer');
            $job = bin2hex(random_bytes(8));
            // Capture values, not the Message and its already-finished cleanup scope.
            $timer = $connection->after($milliseconds, static function (
                Connection $connection,
            ) use ($text, $job): void {
                $connection->set('reminder_timer', null);
                $connection->send('reminder.fired', [
                    'job' => $job,
                    'text' => $text,
                ]);
            });
            if ($timer === false) {
                throw new \RuntimeException('Cannot schedule the reminder.');
            }
            $connection->set('reminder_timer', $timer);
            $connection->send(
                'reminder.scheduled',
                ['job' => $job],
                $message->id(),
            );
        });
        $endpoint->on('reminder.cancel', static function (
            Connection $connection,
            Message $message,
        ): void {
            $connection->send(
                'reminder.cancelled',
                ['cancelled' => self::cancel($connection, 'reminder_timer')],
                $message->id(),
            );
        });
    }

    private static function cancel(Connection $connection, string $key): bool
    {
        $timer = $connection->get($key);
        $connection->set($key, null);
        return is_int($timer) && $connection->cancelTimer($timer);
    }

    private static function room(
        Connection $connection,
        Message $message,
        bool $requireMembership = false,
    ): string {
        $data = $message->validate(['room' => 'required|string']);
        $room = $data['room'];
        if (!self::user($connection)->canJoin($room)) {
            throw new ClientProblem(
                'forbidden_room',
                'This account cannot access that room.',
            );
        }
        if ($requireMembership && !$connection->inRoom($room)) {
            throw new ClientProblem(
                'not_in_room',
                'Join the room before publishing.',
            );
        }
        return $room;
    }

    private static function text(Message $message, int $maxBytes = 2000): string
    {
        $data = $message->validate(['text' => 'required|string']);
        $text = trim($data['text']);
        if ($text === '' || strlen($text) > $maxBytes) {
            throw new ValidationException([
                'text' => "Use a nonempty string of at most {$maxBytes} UTF-8 bytes.",
            ]);
        }
        return $text;
    }

    private static function events(Application $app): EventEmitter
    {
        return $app->container(EventEmitter::class);
    }
}
