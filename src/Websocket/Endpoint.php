<?php

namespace Oktaax\Websocket;

use Closure;
use Oktaax\Event\Event;
use Oktaax\Event\EventEmitter;
use Oktaax\Exception\CleanupException;
use Oktaax\Exception\EventDispatchException;
use Oktaax\Http\Request;
use Throwable;

/**
 * Configure one exact-path WebSocket endpoint before boot; runtime dispatch and broadcasts belong to its kernel.
 */
final class Endpoint
{
    private bool $frozen = false;
    /** Local message handlers, independent of the application's user/IPC emitter. */
    private readonly EventEmitter $events;
    /**
     * Message middleware invoked in registration order for a matched event.
     *
     * @var list<\Closure(\Oktaax\Websocket\Connection, \Oktaax\Websocket\Message, callable(): mixed): mixed>
     */
    private array $middleware = [];
    /**
     * Connection lifecycle observers registered before application boot.
     *
     * @var list<\Closure(\Oktaax\Websocket\Connection): mixed>
     */
    private array $opened = [];
    /**
     * Connection lifecycle observers registered before application boot.
     *
     * @var list<\Closure(\Oktaax\Websocket\Connection): mixed>
     */
    private array $closed = [];
    /**
     * Handshake authorization callback; only an explicit true accepts the connection.
     *
     * @var (\Closure(\Oktaax\Http\Request): bool)|null
     */
    private ?Closure $authorization = null;
    /**
     * Optional replacement for default message error emission.
     *
     * @var (\Closure(\Throwable, \Oktaax\Websocket\Connection, \Oktaax\Websocket\Message|null): mixed)|null
     */
    private ?Closure $errorHandler = null;
    /**
     * Exact permitted Origin header values; null disables origin filtering.
     *
     * @var list<string>|null
     */
    private ?array $allowedOrigins = null;
    private Codec $codec;
    private int $maxBytes = 65536;

    public function __construct(
        private string $path,
        private WebSocketKernel $kernel,
    ) {
        $this->events = new EventEmitter();
        $this->codec = new JsonCodec();
    }
    private function assertMutable(): void
    {
        if ($this->frozen) {
            throw new \LogicException(
                'WebSocket definitions are frozen after boot.',
            );
        }
    }
    public function path(): string
    {
        return $this->path;
    }
    public function freeze(): void
    {
        $this->frozen = true;
    }
    public function messageLimit(): int
    {
        return $this->maxBytes;
    }
    public function messageCodec(): Codec
    {
        return $this->codec;
    }
    public function codec(Codec $codec): self
    {
        $this->assertMutable();
        $this->codec = $codec;
        return $this;
    }
    public function maxMessageBytes(int $bytes): self
    {
        $this->assertMutable();
        if ($bytes < 1) {
            throw new \InvalidArgumentException(
                'Message limit must be positive.',
            );
        }
        $this->maxBytes = $bytes;
        return $this;
    }
    /**
     * Authorize before HTTP upgrade; only true accepts a valid handshake.
     *
     * @param callable(\Oktaax\Http\Request): bool $callback
     * @throws \LogicException When endpoint definitions are frozen.
     */
    public function authorize(callable $callback): self
    {
        $this->assertMutable();
        $this->authorization = Closure::fromCallable($callback);
        return $this;
    }
    /**
     * Require an Origin header matching one of the supplied exact values.
     *
     * @param list<string> $origins
     */
    public function origins(array $origins): self
    {
        $this->assertMutable();
        foreach ($origins as $origin) {
            if (!is_string($origin) || $origin === '') {
                throw new \InvalidArgumentException(
                    'Origins must be nonempty strings.',
                );
            }
        }
        $this->allowedOrigins = $origins;
        return $this;
    }
    public function allows(Request $request): bool
    {
        if (
            $this->allowedOrigins !== null &&
            !in_array($request->header('origin'), $this->allowedOrigins, true)
        ) {
            return false;
        }
        return $this->authorization === null ||
            ($this->authorization)($request) === true;
    }
    /**
     * Register a unique application event handler; return values are ignored.
     *
     * @param callable(\Oktaax\Websocket\Connection, \Oktaax\Websocket\Message): mixed $handler
     * @throws \LogicException When endpoint definitions are frozen.
     */
    public function on(string $event, callable $handler): self
    {
        $this->assertMutable();
        new Message($event);
        if ($this->events->hasListeners($event)) {
            throw new \InvalidArgumentException(
                'WebSocket event is already registered: ' . $event,
            );
        }
        $handler = Closure::fromCallable($handler);
        $this->events->on(
            $event,
            /** @param Event<array{connection: Connection, message: Message, result: mixed}> $dispatch */
            static function (Event $dispatch) use ($handler): void {
                $dispatch->data['result'] = $handler(
                    $dispatch->data['connection'],
                    $dispatch->data['message'],
                );
            },
        );
        return $this;
    }
    /**
     * Register message middleware; invoke next() to continue the chain.
     *
     * @param callable(\Oktaax\Websocket\Connection, \Oktaax\Websocket\Message, callable(): mixed): mixed ...$middleware
     * @throws \LogicException When endpoint definitions are frozen.
     */
    public function use(callable ...$middleware): self
    {
        $this->assertMutable();
        foreach ($middleware as $callback) {
            $this->middleware[] = Closure::fromCallable($callback);
        }
        return $this;
    }
    /**
     * Observe an accepted connection once its native upgrade is active.
     *
     * @param callable(\Oktaax\Websocket\Connection): mixed $callback
     * @throws \LogicException When endpoint definitions are frozen.
     */
    public function onOpen(callable $callback): self
    {
        $this->assertMutable();
        $this->opened[] = Closure::fromCallable($callback);
        return $this;
    }
    /**
     * Observe closure after connection cleanup and before retained connection state is cleared.
     *
     * @param callable(\Oktaax\Websocket\Connection): mixed $callback
     * @throws \LogicException When endpoint definitions are frozen.
     */
    public function onClose(callable $callback): self
    {
        $this->assertMutable();
        $this->closed[] = Closure::fromCallable($callback);
        return $this;
    }
    /**
     * Replace default message error emission; protocol errors still close the connection.
     *
     * @param callable(Throwable, \Oktaax\Websocket\Connection, \Oktaax\Websocket\Message|null): mixed $callback
     * @throws \LogicException When endpoint definitions are frozen.
     */
    public function onError(callable $callback): self
    {
        $this->assertMutable();
        $this->errorHandler = Closure::fromCallable($callback);
        return $this;
    }
    public function opened(Connection $connection): void
    {
        foreach ($this->opened as $callback) {
            $callback($connection);
        }
    }
    public function closed(Connection $connection): void
    {
        CleanupException::run(
            array_map(
                fn($callback) => fn() => $callback($connection),
                $this->closed,
            ),
        );
    }
    public function dispatch(Connection $connection, Message $message): bool
    {
        if (!$this->events->hasListeners($message->event())) {
            return false;
        }
        $this->invoke(0, $connection, $message);
        return true;
    }
    /**
     * Invoke the next middleware or the terminal handler without retaining the message.
     */
    private function invoke(
        int $index,
        Connection $connection,
        Message $message,
    ): mixed {
        if (!isset($this->middleware[$index])) {
            $dispatch = new Event($message->event(), [
                'connection' => $connection,
                'message' => $message,
                'result' => null,
            ]);
            try {
                $this->events->emit($message->event(), $dispatch);
            } catch (EventDispatchException $error) {
                // Exactly one listener is registered; preserve its original error for WS policies.
                throw $error->exceptions()[0] ?? $error;
            }
            return $dispatch->data['result'];
        }
        return $this->middleware[$index](
            $connection,
            $message,
            fn() => $this->invoke($index + 1, $connection, $message),
        );
    }
    public function handleError(
        Throwable $error,
        Connection $connection,
        ?Message $message,
    ): bool {
        if ($this->errorHandler === null) {
            return false;
        }
        ($this->errorHandler)($error, $connection, $message);
        return true;
    }
    /**
     * Send to this endpoint locally and through IPC to peer HTTP workers; delivery is best-effort.
     */
    public function broadcast(string $event, mixed $data = null): void
    {
        $this->target()->send($event, $data);
    }
    public function target(): BroadcastTarget
    {
        return new BroadcastTarget($this->kernel, $this);
    }
    /**
     * Create an immutable target filtering endpoint connections by room membership.
     *
     * @throws \InvalidArgumentException When the room name is invalid.
     */
    public function room(string $name): BroadcastTarget
    {
        Connection::validateRoom($name);
        return new BroadcastTarget($this->kernel, $this, $name);
    }
}
