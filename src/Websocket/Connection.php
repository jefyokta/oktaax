<?php

namespace Oktaax\Websocket;

use Closure;
use Oktaax\Application;
use Oktaax\Application\ConnectionScope;
use Oktaax\Exception\CleanupException;
use Oktaax\Http\Request;
use Swoole\Timer;

/**
 * Own handshake metadata, application state, subscriptions, and timers for one accepted socket.
 */
final class Connection
{
    private ConnectionScope $scope;
    /**
     * Application-defined connection state, cleared after close hooks.
     *
     * @var array<string, mixed>
     */
    private array $attributes = [];
    /**
     * Room memberships owned by this connection and discarded on close.
     *
     * @var array<string, true>
     */
    private array $rooms = [];
    /**
     * Owned timer IDs; cleared on disconnect or worker drain.
     *
     * @var array<int, true>
     */
    private array $timers = [];
    private bool $activated = false;

    /**
     * Bind a native descriptor to its owning application, endpoint, transport, and idempotent disconnect callback.
     *
     * @param \Closure(): mixed $disconnected
     * @internal Connections are created by WebSocketKernel.
     */
    public function __construct(
        private int $fd,
        private Endpoint $endpoint,
        private ?Request $request,
        private Application $application,
        private Transport $transport,
        private Closure $disconnected,
    ) {
        $this->scope = new ConnectionScope();
        $this->scope->bind($application);
        $this->scope->defer(function () {
            foreach (array_keys($this->timers) as $timer) {
                $this->cancelTimer($timer);
            }
        });
    }
    public function id(): int
    {
        return $this->fd;
    }
    public function endpoint(): Endpoint
    {
        return $this->endpoint;
    }
    /**
     * Return handshake metadata until closure finishes; this is not the current HTTP request.
     */
    public function request(): ?Request
    {
        return $this->request;
    }
    public function isClosed(): bool
    {
        return $this->scope->isClosed();
    }
    public function isOpen(): bool
    {
        return !$this->isClosed() && $this->transport->isOpen($this->fd);
    }
    public function isActivated(): bool
    {
        return $this->activated;
    }
    public function activate(): bool
    {
        if ($this->activated || $this->isClosed()) {
            return false;
        }
        $this->activated = true;
        return true;
    }
    public function set(string $key, mixed $value): self
    {
        if ($this->isClosed()) {
            throw new \LogicException('Connection is closed.');
        }
        $this->attributes[$key] = $value;
        return $this;
    }
    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->attributes)
            ? $this->attributes[$key]
            : $default;
    }
    /**
     * Register connection cleanup, run once in reverse order inside the owning application scope.
     *
     * @param \Closure(): mixed $callback
     */
    public function defer(Closure $callback): void
    {
        $this->scope->defer(fn() => $this->application->within($callback));
    }
    public static function validateRoom(string $room): void
    {
        if (
            $room === '' ||
            strlen($room) > 128 ||
            preg_match('/[\x00-\x1f\x7f]/', $room)
        ) {
            throw new \InvalidArgumentException(
                'Room must be a nonempty name of at most 128 bytes.',
            );
        }
    }
    public function join(string $room): self
    {
        self::validateRoom($room);
        if ($this->isClosed()) {
            throw new \LogicException('Connection is closed.');
        }
        $this->rooms[$room] = true;
        return $this;
    }
    public function leave(string $room): self
    {
        unset($this->rooms[$room]);
        return $this;
    }
    public function inRoom(string $room): bool
    {
        return isset($this->rooms[$room]);
    }
    /**
     * Return joined room names; membership itself does not grant authorization.
     *
     * @return list<string>
     */
    public function rooms(): array
    {
        return array_keys($this->rooms);
    }
    /**
     * Encode an application event and return whether the transport accepted it; a failed write closes the scope.
     *
     * @throws \Oktaax\Websocket\ProtocolException When event or correlation ID syntax is invalid.
     * @throws \JsonException When the default JSON codec cannot encode the payload.
     */
    public function send(
        string $event,
        mixed $data = null,
        ?string $id = null,
    ): bool {
        if ($this->isClosed()) {
            return false;
        }
        $codec = $this->endpoint->messageCodec();
        return $this->sendEncoded(
            $codec->encode(new Message($event, $data, $id)),
            $codec->opcode(),
        );
    }
    /**
     * Send a pre-encoded frame payload; remove stale connections or close failed deliveries.
     *
     * @internal Used by broadcasts and encoded send operations.
     */
    public function sendEncoded(string $payload, int $opcode): bool
    {
        if ($this->isClosed()) {
            return false;
        }
        if (!$this->transport->isOpen($this->fd)) {
            ($this->disconnected)();
            return false;
        }
        if ($this->transport->send($this->fd, $payload, $opcode)) {
            return true;
        }
        $this->close(1011, 'Delivery failed');
        return false;
    }
    /**
     * Attempt a protocol close and release connection resources even when transport closure fails.
     *
     * @throws \InvalidArgumentException When the wire code is invalid or the UTF-8 reason exceeds 123 bytes.
     */
    public function close(int $code = 1000, string $reason = ''): bool
    {
        if (
            !(
                ($code >= 1000 &&
                    $code <= 1014 &&
                    !in_array($code, [1004, 1005, 1006], true)) ||
                ($code >= 3000 && $code <= 4999)
            ) ||
            strlen($reason) > 123 ||
            preg_match('//u', $reason) !== 1
        ) {
            throw new \InvalidArgumentException(
                'Invalid WebSocket close code or UTF-8 reason (maximum 123 bytes).',
            );
        }
        if ($this->isClosed()) {
            return false;
        }
        try {
            return $this->transport->close($this->fd, $code, $reason);
        } finally {
            ($this->disconnected)();
        }
    }
    /**
     * Attempt all connection cleanup and close hooks, then clear handshake metadata and application state.
     *
     * @internal Called after the registry removes this connection.
     * @throws \Throwable When cleanup or close observers fail.
     */
    public function finish(): void
    {
        if ($this->isClosed()) {
            return;
        }
        try {
            $this->application->within(
                fn() => CleanupException::run([
                    fn() => $this->scope->close(),
                    fn() => $this->activated
                        ? $this->endpoint->closed($this)
                        : null,
                ]),
            );
        } finally {
            $this->request = null;
            $this->attributes = [];
            $this->rooms = [];
        }
    }
    /**
     * Schedule recurring connection work in milliseconds; closure and worker drain cancel it.
     *
     * @param callable(\Oktaax\Websocket\Connection): mixed $callback
     * @throws \InvalidArgumentException When the interval is not positive.
     * @throws \LogicException When the connection or worker is closing.
     */
    public function tick(int $milliseconds, callable $callback): int|false
    {
        return $this->schedule($milliseconds, $callback, true);
    }
    /**
     * Schedule one connection callback after a millisecond delay; closure cancels it.
     *
     * @param callable(\Oktaax\Websocket\Connection): mixed $callback
     * @throws \InvalidArgumentException When the interval is not positive.
     * @throws \LogicException When the connection or worker is closing.
     */
    public function after(int $milliseconds, callable $callback): int|false
    {
        return $this->schedule($milliseconds, $callback, false);
    }
    /**
     * Create an owned native timer and restore application scope around its callback.
     *
     * @param callable(\Oktaax\Websocket\Connection): mixed $callback
     * @throws \InvalidArgumentException When the interval is not positive.
     * @throws \LogicException When the connection or worker is closing.
     */
    private function schedule(
        int $milliseconds,
        callable $callback,
        bool $repeat,
    ): int|false {
        if ($milliseconds < 1) {
            throw new \InvalidArgumentException(
                'Timer interval must be positive.',
            );
        }
        if (!$this->isOpen() || !$this->application->workers()->isRunning()) {
            throw new \LogicException(
                'Cannot schedule work for a closed or stopping connection.',
            );
        }
        $timer = null;
        $invoke = function () use ($callback, $repeat, &$timer) {
            // Native timers invoke this callback after their ID has been assigned.
            /** @var int $timer */
            if (!$repeat) {
                unset($this->timers[$timer]);
                $this->application->workers()->forgetTimer($timer);
            }
            if (!$this->isOpen()) {
                ($this->disconnected)();
                return;
            }
            $this->application->within(function () use ($callback) {
                try {
                    $callback($this);
                } catch (\Throwable $error) {
                    $this->application->websockets()->report($error, $this);
                }
            });
        };
        $timer = $repeat
            ? Timer::tick($milliseconds, $invoke)
            : Timer::after($milliseconds, $invoke);
        if ($timer !== false) {
            try {
                $this->application->workers()->trackTimer($timer);
            } catch (\Throwable $error) {
                Timer::clear($timer);
                throw $error;
            }
            $this->timers[$timer] = true;
        }
        return $timer;
    }
    public function cancelTimer(int $timer): bool
    {
        if (!isset($this->timers[$timer])) {
            return false;
        }
        unset($this->timers[$timer]);
        $this->application->workers()->forgetTimer($timer);
        return Timer::exists($timer) && Timer::clear($timer);
    }
}
