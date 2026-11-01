# Streaming and server-sent events

[Guide index](README.md) · [Response recipe](../examples/guide/responses.php) ·
[SSE recipe](../examples/guide/sse.php)

## Finite streams

```php
$app->get('/stream', function ($request, $response) {
    return $response->stream(
        function (callable $write) {
            foreach (["first\n", "second\n"] as $chunk) {
                if (!$write($chunk)) {
                    return;
                }
            }
        },
        200,
        ['content-type' => 'text/plain; charset=utf-8'],
    );
});
```

The callback receives a boolean-returning write function. Stop producing data on
`false`. The emitter ends a still-writable response when the callback returns.
Generators are also incremental: set headers first and yield scalar or
`Stringable` chunks. Large strings passed to `end()` are chunked according to
`app.chunk_size` (8192 bytes by default).

Set all headers/status before the first write. HEAD sends no body bytes, but
handler and producer code still execute; avoid side effects in GET handlers.
A failed write aborts the connection and stops automatic chunking/generator
consumption. Streaming is not automatic pagination: fetch/produce data in bounded
batches yourself.

## SSE binding and framing

```php
$app->get('/events', function ($request, $response) {
    $sse = $response->sse();
    $sse->on('start', function () use ($sse) {
        $sse->send(['connected' => true], ['event' => 'ready', 'id' => '1']);
        $sse->stop();
    });
    return $sse;
});
```

`$response->sse($headers = [])` binds the response immediately. Returning it
starts it through the emitter. You may also return an unbound
`new SseResponse()` for automatic binding or call `start($response)` explicitly.
Calling unbound `start()` without a response throws; no ambient response lookup
exists.

Starting sets `Content-Type: text/event-stream; charset=utf-8`, `Cache-Control:
no-cache`, and `X-Accel-Buffering: no`, then prevents automatic response end.
`send($data, $options)` JSON-encodes nonstrings and frames each text line as
`data:`. Options support `event`, `id`, and a nonnegative integer `retry` in
milliseconds. Event/id fields cannot contain CR, LF, or NUL. JSON encoding and
invalid-field errors propagate. `comment('heartbeat')` sends a comment frame.
`send()` and `comment()` return the write result.

`stop()` ends the response once. Sending before start or after stop throws.
`on('start', ...)` and `on('close', ...)` are connection lifecycle events, distinct
from the named events sent to a browser.

## Subscriptions and cancellation

```php
use Oktaax\Event\EventEmitter;

$app->get('/subscribe', function ($request, $response) use ($app) {
    $events = $app->container(EventEmitter::class);
    $sse = $response->sse();
    $listener = fn($event) => $sse->send($event->data, ['event' => 'message']);
    $events->on('message', $listener);
    $request->connection()->defer(fn() => $events->off('message', $listener));
    return $sse;
});
```

Keep subscription cleanup in the **connection** scope. Request cleanup runs as
soon as the handler has finished. A response ending, failed write, detected native
disconnect, or worker teardown closes the connection scope and cancels its
watchers. SSE also polls writability; disconnect detection can be delayed until a
native event, poll, or write failure.

For a connection-owned producer timer, use a worker timer and cancel it from
`connection()->defer()`. The [complete recipe](../examples/guide/sse.php) sends
three ticks, closes the stream, and forgets its timer. Long-running streams should
send heartbeats at an interval appropriate for their proxy timeouts.

## Browser and curl clients

```js
const stream = new EventSource('/subscribe');
stream.addEventListener('message', (event) => {
    console.log(JSON.parse(event.data));
});
// When leaving the screen:
// stream.close();
```

```sh
php examples/guide/serve.php sse
# In another terminal; this ends after three ticks:
curl -N http://127.0.0.1:3000/events
```

The recipe's `/subscribe` stays open; POST `/publish` with a JSON `message` from
another terminal. SSE browser reconnection and `Last-Event-ID` do not create a
server replay buffer automatically. Persist and replay events yourself if needed.
Reverse proxies must allow streaming and have suitable idle timeouts; see
[operations](configuration.md).
