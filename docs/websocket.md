# WebSocket endpoints

[Guide index](README.md) · [Runnable server](../examples/guide/websocket.php) ·
[Browser client](../examples/guide/views/websocket.html)

## Register an endpoint

WebSocket is an application-owned module. Register endpoints alongside HTTP routes;
`ProcessRuntime` selects `Swoole\WebSocket\Server` automatically when needed.
There is no trait, subclass, or manually configured Swoole table to install.

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Oktaax\Application;
use Oktaax\Runtime\Swoole\ProcessRuntime;
use Oktaax\Websocket\Connection;
use Oktaax\Websocket\Message;

$app = new Application();
$app->get('/health', fn() => ['ok' => true]);
$ws = $app->websocket('/ws');
$ws->on('echo', function (Connection $connection, Message $message) {
    $connection->send('echo', $message->data(), $message->id());
});
$app->run(new ProcessRuntime());
```

Connect to `ws://127.0.0.1:3000/ws`. An `Oktaax` instance with `listen()` works
with the same endpoint registration. `CoroutineRuntime` rejects WS endpoints
before boot or socket creation. Process runtime uses `server.dispatch_mode=2`
for stable connection ownership and rejects a different configured dispatch mode
before starting. Custom runtime implementations declare their
capability through `ServerRuntime::supportsWebSockets()`.

Paths match exactly, excluding query strings. They do not accept FastRoute
parameters or apply a group prefix. An upgrade to an unregistered path gets HTTP 404. Ordinary HTTP requests still go through HTTP routes. Definitions, including
a retained endpoint's hooks and middleware, freeze at application boot.

Each endpoint owns a private local `EventEmitter` for message handlers. It is
independent of the application's public event service and does not forward
messages through IPC. `on()` registers one handler per event name; duplicate
registration throws. Callbacks still receive `(Connection, Message)`, and
endpoint error policies receive the original handler exception.

For a complete runnable implementation with a browser client, see the
[WebSocket workspace](../examples/guide/websocket-complete/README.md). Run
`php examples/guide/serve.php websocket-complete` and follow its walkthrough.

## Wire messages

```json
{ "event": "echo", "data": { "text": "Hello" }, "id": "request-1" }
```

The default `JsonCodec` accepts a JSON text object with `event`, optional `data`,
and optional `id`. It rejects other top-level fields. Event names and non-null
IDs must be nonempty strings of at most 128 bytes without control characters.
`data` can be any JSON value; absent data becomes null. Objects decode to PHP
associative arrays, and false/null values remain intact.

`Message::event()` returns the event name; `data()` returns only its payload,
without another nested envelope. `id()` is the optional correlation ID.
`Connection::send($event, $data = null, $id = null): bool` encodes a matching
outbound envelope. Replies include an ID only when explicitly supplied; there
is no implicit acknowledgement or automatic serialization of handler returns.

```js
const socket = new WebSocket('ws://127.0.0.1:3000/ws');
socket.onopen = () => {
    socket.send(
        JSON.stringify({ event: 'echo', data: 'Hello', id: 'request-1' }),
    );
};
socket.onmessage = (event) => console.log(JSON.parse(event.data));
```

Expected reply: `{"event":"echo","data":"Hello","id":"request-1"}`.
The maintained recipe uses `/socket` and includes a browser page at `/`:

```sh
php examples/guide/serve.php websocket
```

## Authorize before the upgrade

```php
use Oktaax\Http\Request;

$ws->origins(['https://app.example.com']);
$ws->authorize(function (Request $request): bool {
    // Replace this demo cookie check with your authentication service.
    return $request->cookie('demo_session') === 'local-demo';
});
```

`authorize()` runs before HTTP 101 and must return **true** to accept. False
returns HTTP 403; throwing `HttpException` selects its status/headers. An origin
allowlist also requires the Origin header to be present and match exactly.
Without an allowlist, no origin restriction is added. Without an authorization
callback, the endpoint accepts otherwise valid handshakes. The public echo recipe
intentionally has neither restriction.

HTTP route middleware is separate; it does not automatically authenticate a WS
handshake or individual message. Native browser WebSocket clients can send
cookies but cannot set arbitrary Authorization headers through their constructor.
Choose your own session/token lookup and validate it in the authorization callback.

The handshake validates method, Upgrade/Connection headers, a 16-byte decoded
Sec-WebSocket-Key, and version 13. Bad input gets 400; unsupported version gets
426 with `Sec-WebSocket-Version: 13`. Unexpected authorization failures produce
500 with a generic error, and are logged server-side. Request `defer()` callbacks
run after handshake processing. Do not allocate connection resources there;
allocate them in `onOpen()` and register them on the connection.

## Connection identity and hooks

```php
$ws->onOpen(function (Connection $connection) {
    $connection->set('messages', 0);
    $connection->defer(function () {
        // Release subscriptions or application resources for this connection.
    });
});
$ws->onClose(function (Connection $connection) {
    error_log('Closed connection ' . $connection->id());
});
```

The same `Connection` instance is passed to every message for its socket.
`id()` is the native connection descriptor, not a persistent user ID. Use
`set($key, $value)` and `get($key, $default)` for connection state. `request()`
provides the original handshake request until cleanup completes. Values are
cleared after close hooks so retained connection objects do not keep request
state alive. Closed connections are removed from the worker's registry.
Reconnecting creates new connection state; clients must authenticate and join
their rooms again.

Callbacks enter their owning application's execution scope. `app()` works there;
`app()->getRequest()` does not represent the handshake or a WS message. Use the
supplied objects. Each connection is local to its owning worker. Message handlers
that yield can overlap; serialize application operations yourself when required.

`close($code = 1000, $reason = ''): bool` disconnects and releases resources once.
Reasons must be valid UTF-8 up to 123 bytes and codes must be valid wire close
codes. `isOpen()` and `isClosed()` inspect state. Failed writes return false and
close the connection scope. `onClose()` runs once for an activated connection,
even if a cleanup callback fails. All connection cleanup is attempted in reverse
registration order before close hooks. Hooks can inspect state before it is cleared.

Worker shutdown releases every owned connection and timer. A 1001 close is
attempted when possible; native server shutdown can close a socket without a
close frame. Clients must handle transport closure as well as protocol close.
A failed open hook closes with 1011 after running cleanup.

## Validate and clean up each message

```php
$ws->on('chat.send', function (Connection $connection, Message $message) {
    $data = $message->validate(['text' => 'required|string']);
    $message->defer(function () {
        /* Release this message's temporary resources. */
    });
    $connection->send('chat.received', $data, $message->id());
});
```

Validation follows the [request validator](requests.md#validation), returns only
ruled keys, and rejects scalar data when a field map is expected. Message cleanup
runs once in a `finally` block on success or failure. Connection state and its
subscriptions survive message cleanup.

Endpoint middleware receives `(Connection, Message, callable $next)`:

```php
$ws->use(function (Connection $connection, Message $message, callable $next) {
    if ($connection->get('suspended', false)) {
        $connection->send('error', ['code' => 'suspended'], $message->id());
        return;
    }
    return $next();
});
```

Middleware runs in registration order for matched events. Call `$next()` to
continue; sending a response and returning stops the chain. Handler return values
are ignored. Pass closures, invokable objects, or other actual callables; no
implicit controller construction or service autowiring is performed.

## Timers owned by the connection

```php
$ws->onOpen(function (Connection $connection) {
    $connection->tick(
        15000,
        fn(Connection $connection) => $connection->send('heartbeat', [
            'time' => time(),
        ]),
    );
});
```

`tick($milliseconds, $callback)` repeats; `after()` runs once. Both return a timer
ID or false. Callbacks receive the owning connection and run in its application
scope. `cancelTimer($id)` only cancels a timer owned by this connection. Timers
are automatically cleared and forgotten on disconnect or worker drain; scheduling
on closed/stopping connections throws. Callback failures enter endpoint error
policy. Protocol ping/pong remains a separate native transport concern.

## Rooms and broadcasts

```php
$ws->on('chat.join', function (Connection $connection, Message $message) {
    $data = $message->validate(['room' => 'required|string']);
    // Authorize this user's membership before joining the room.
    $connection->join($data['room']);
    $connection->send('chat.joined', $data);
});
$ws->on('chat.send', function (Connection $connection, Message $message) use (
    $ws,
) {
    $data = $message->validate([
        'room' => 'required|string',
        'text' => 'required|string',
    ]);
    if (!$connection->inRoom($data['room'])) {
        return;
    }
    $ws->room($data['room'])
        ->except($connection)
        ->send('chat.message', ['text' => $data['text']]);
});
```

`join`, `leave`, `inRoom`, and `rooms` manage membership in connection memory.
Room names are nonempty, at most 128 bytes, and exclude control characters.
Membership alone is not authorization; your handlers decide who can join/publish.

`$ws->broadcast($event, $data)` sends to all accepted connections on that endpoint,
including the sender. `room($name)` filters membership; `target()->except($connection)`
filters the sender without a room. Targets are immutable: adding an exclusion
returns a new target and does not alter a previously retained target. Excluding
a connection from another endpoint is rejected.

Each worker keeps its own connection registry. Broadcast encodes once, delivers
locally, and forwards a serializable internal message through IPC to other HTTP
workers when `server.worker_num > 1`. Receivers filter by endpoint, room, and
exclusion without rebroadcast. Delivery to peers is asynchronous and best-effort;
broadcast methods return void and do not acknowledge remote delivery. A failed
individual write cleans that connection while other recipients continue. Rooms
and broadcasts cover one native server's workers, not multiple server instances;
use your own external broker to span deployments.

## Errors and codecs

Recoverable failures send an `error` event, copying the incoming ID if available:

```json
{
    "event": "error",
    "data": {
        "code": "unknown_event",
        "message": "No handler is registered for this event."
    },
    "id": "request-1"
}
```

Validation uses `validation_failed` plus an `errors` map. Unexpected handler errors
use `internal_error`, log details server-side, and keep the connection available.
Debug mode does not inject HTML diagnostics or stack traces into WS packets.

Malformed JSON closes with 1007; invalid envelopes with 1008; unsupported binary
with 1003; an oversized message with 1009. A structured error is attempted before
closing. `maxMessageBytes($bytes)` sets the per-endpoint inbound limit, default
65536 bytes. Native `server.package_max_length` can enforce a transport-level
limit before a full message reaches the kernel.

`onError(function (Throwable $error, Connection $connection, ?Message $message)
{ ... })` replaces default message error emission. Protocol failures still close
the connection. A throwing error handler closes with 1011. HTTP handshake errors
use their HTTP response path rather than the endpoint message error callback.

`codec(Codec $codec)` replaces the default JSON codec before boot. Implement
`decode(string $payload, int $opcode): Message`, `encode(Message $message): string`,
and `opcode(): int`. Codecs own application framing and must reject bad input
with `ProtocolException`; the kernel still enforces byte limits and cleanup.
The old ad hoc binary packet helper, trait, `Client`/`Server` wrappers, class-based
events, channels, table facade, and `xsocket()` factory have been removed. See
[migration](migration.md#websocket-composition).
