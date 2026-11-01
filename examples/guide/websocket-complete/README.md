# Complete WebSocket example

[Example catalog](../../README.md) · [WebSocket API](../../../docs/websocket.md) · [Entrypoint](../websocket-complete.php)

A runnable workspace covering the public WebSocket API with authenticated chat,
room authorization, connection state, timers, cleanup, broadcasts, backend
events, and custom codecs. It uses two process workers by default and needs no
database, external broker, frontend build, or additional Composer package.

## Run

From the repository root, with CLI Swoole enabled:

```sh
composer install
php examples/guide/serve.php websocket-complete
```

Open **http://127.0.0.1:3000/**. Sign in, connect, and inspect the live wire log.
The simple `websocket` recipe remains available separately. This complete recipe
requires process runtime; coroutine runtime rejects WebSocket endpoints.

| Demo account | Password     | Allowed rooms                     | Admin monitor / announcements |
| ------------ | ------------ | --------------------------------- | ----------------------------- |
| `alice`      | `alice-demo` | `lobby`, `engineering`            | No                            |
| `bob`        | `bob-demo`   | `lobby`, `design`                 | No                            |
| `admin`      | `admin-demo` | All four rooms, including `staff` | Yes                           |

These fixed credentials are a local demonstration, not production password
storage. Replace `DemoAuth` with your application's identity/session service.
Use a separate browser profile or private window for another account; tabs in
one browser profile share the session cookie.

## Files

| File                                                                       | Responsibility                                                             |
| -------------------------------------------------------------------------- | -------------------------------------------------------------------------- |
| [websocket-complete.php](../websocket-complete.php)                        | Application configuration, HTTP login/logout, assets, backend notice route |
| [DemoAuth.php](DemoAuth.php), [Identity.php](Identity.php)                 | Signed expiring HttpOnly session cookie and server-owned room permissions  |
| [SocketDemo.php](SocketDemo.php)                                           | Endpoints, middleware, handlers, lifecycle, cleanup, and error policy      |
| [ClientProblem.php](ClientProblem.php)                                     | Expected failures with safe public messages and optional close codes       |
| [AlternateJsonCodec.php](AlternateJsonCodec.php)                           | Adapting another application's envelope                                    |
| [BinaryJsonCodec.php](BinaryJsonCodec.php)                                 | The normal envelope in binary UTF-8 JSON frames                            |
| [client.html](client.html), [client.js](client.js), [style.css](style.css) | Browser controls, correlation, reconnect, and bounded wire log             |

## Endpoints and framing

| Path                | Access            | Frame format        | Features                                    |
| ------------------- | ----------------- | ------------------- | ------------------------------------------- |
| `/socket/chat`      | Signed-in account | JSON text           | All chat, room, timer, and preview handlers |
| `/socket/status`    | Admin             | JSON text           | Worker-local monitoring and basic handlers  |
| `/socket/alternate` | Signed-in account | Alternate JSON text | Basic handlers and custom envelope          |
| `/socket/binary`    | Signed-in account | Binary UTF-8 JSON   | Basic handlers and custom opcode            |

The default envelope is:

```json
{ "event": "echo", "data": { "text": "Hello" }, "id": "request-1" }
```

Alternate framing uses different field names:

```json
{ "type": "echo", "payload": { "text": "Hello" }, "request_id": "request-1" }
```

Binary framing carries the default JSON as UTF-8 bytes. The browser uses
`TextEncoder`, `binaryType = 'arraybuffer'`, and `TextDecoder`; it does not send
arbitrary file contents to this JSON codec. The client adapts to the selected
endpoint automatically, including the raw editor.

`id` correlates one request and reply. It is optional, must be a nonempty string
of at most 128 bytes, and is not an idempotency key. Handler return values are
not automatically sent: handlers explicitly call `Connection::send()`.

## Event catalog

Basic handlers exist on all four endpoints:

| Send           | Data           | Response                                                           |
| -------------- | -------------- | ------------------------------------------------------------------ |
| `echo`         | Any JSON value | `echo`, preserving data and request ID                             |
| `ping`         | `{}`           | `pong` with server time                                            |
| `state.get`    | `{}`           | `state`: identity, memberships, handled-message count, timer state |
| `client.close` | `{}`           | `closing`, then close code `1000`                                  |
| `demo.fail`    | `{}`           | Generic `internal_error`; details are logged only on the server    |

Additional chat handlers:

| Send                | Data example                                 | Behavior                                                                               |
| ------------------- | -------------------------------------------- | -------------------------------------------------------------------------------------- |
| `chat.join`         | `{"room":"engineering"}`                     | Checks permission before joining; replies `chat.joined`                                |
| `chat.leave`        | `{"room":"engineering"}`                     | Leaves the room; replies `chat.left`                                                   |
| `chat.send`         | `{"room":"lobby","text":"Hello"}`            | Checks permission and membership, broadcasts `chat.message`, replies `chat.sent`       |
| `chat.typing`       | `{"room":"lobby"}`                           | Sends `chat.typing` to room peers excluding the sender; replies `chat.typing.accepted` |
| `admin.announce`    | `{"text":"Maintenance soon"}`                | Admin-only endpoint broadcast; replies `admin.announced`                               |
| `clock.start`       | `{"milliseconds":1000}`                      | Replaces the connection's clock; sends `clock.tick` periodically                       |
| `clock.stop`        | `{}`                                         | Cancels the clock; replies `clock.stopped`                                             |
| `reminder.schedule` | `{"milliseconds":2000,"text":"Hello later"}` | Replaces a one-shot timer; replies now, sends `reminder.fired` later                   |
| `reminder.cancel`   | `{}`                                         | Cancels a pending reminder                                                             |
| `document.preview`  | `{"text":"Temporary content"}`               | Computes byte length and SHA-256 using a temporary stream closed by `Message::defer()` |
| `heartbeat.pong`    | `{"nonce":"value-from-server"}`              | Updates liveness after checking the nonce; no application reply                        |

The server also sends `ready`, `presence.joined`, `presence.left`,
`heartbeat.ping`, `session.expired`, and `backend.notice`. The monitor sends
`status.tick` every two seconds. Its connection count is **worker-local**, not a
cluster-wide total. Native connection IDs are also worker-local.

## Walk through the browser controls

1. Sign in as Alice. Chat automatically joins `lobby`; `ready` identifies the worker.
2. Join `engineering`, send a message, leave, then try sending again. The server
   rejects publishing without membership. A raw join to `staff` is forbidden.
3. Open another account in a different browser profile. Both can chat in `lobby`;
   the typing event excludes its sender. Room broadcasts also span workers.
4. Start/stop the clock, schedule/cancel a reminder, and preview some text. These
   show recurring timers, one-shot timers, cancellation, and per-message cleanup.
5. Disconnect and reconnect. Confirm room and clock subscriptions restore while
   chat messages and reminders are not automatically replayed.
6. Sign in as Admin. Try an endpoint announcement and the HTTP backend notice,
   then choose the monitor endpoint. Choose the alternate or binary endpoint to
   exercise custom framing with the same basic operations.
7. Try unknown events, validation failures, and internal errors. They return an
   `error` event and keep the connection available. Bad JSON, the wrong opcode,
   and oversize frames deliberately close it; reconnect manually afterward.
8. Disable heartbeat responses to observe a `4408` timeout, or set a short
   session lifetime to observe `4401`. Reconnect requires signing in again once
   the session expires.

## HTTP → backend event → WebSocket

The admin notice button calls `POST /demo/notice`. That route emits a serializable
`demo.notice` through the application's worker event service. Each chat connection
subscribes locally, forwards `backend.notice`, and registers `off()` with
`Connection::defer()`. Disconnect therefore removes the listener that retains the
connection. The public emitter forwards notices through worker IPC; endpoint
message emitters remain private and local.

An HTTP session can also be exercised with curl:

```sh
curl -i -c /tmp/oktaax-ws-demo.cookies http://127.0.0.1:3000/demo/session \
  -H 'Origin: http://127.0.0.1:3000' \
  -H 'Content-Type: application/json' \
  -d '{"username":"admin","password":"admin-demo"}'

curl -i -b /tmp/oktaax-ws-demo.cookies http://127.0.0.1:3000/demo/notice \
  -H 'Origin: http://127.0.0.1:3000' \
  -H 'Content-Type: application/json' \
  -d '{"text":"Hello from HTTP"}'
```

The notice is visible to open chat clients. The cookie is HttpOnly, SameSite
Strict, and sent automatically by a same-origin browser WebSocket; tokens do not
appear in socket URLs. Login, logout, and notice routes require an allowed
Origin. Ordinary HTTP failures use the application's existing HTTP error policy.

## Limits, errors, and cleanup

- Origin checks and cookie verification run before upgrade. Unauthorized or
  wrong-origin upgrades receive HTTP 403; unknown paths receive HTTP 404.
- Room access comes from the authenticated identity, never a client-supplied
  role or sender name. `chat.send` rechecks both permission and membership.
- Endpoint messages are limited to 16 KiB; the native package limit is 128 KiB.
  Text is limited to 2000 UTF-8 bytes, preview text to 8192 bytes, clock intervals
  to 500–10000 ms, and reminder delays to 100–30000 ms.
- Registered events allow 30 messages per ten-second window per connection.
  Further attempts get `rate_limited`; repeated violations close with `4408`.
  Unknown events are rejected by the kernel before endpoint middleware, so this
  limiter is not a complete ingress or connection limiter.
- Validation and expected business failures expose safe messages with correlation
  IDs. Unexpected exceptions expose no stack trace or exception message to the client.
- Invalid JSON closes with `1007`, invalid envelopes with `1008`, unsupported
  opcodes with `1003`, and oversize messages with `1009`. Server drain uses `1001`.
- Sessions are checked at handshake, in message middleware, and by an expiry
  timer. Heartbeat pings run every 15 seconds; missing pongs close after the
  configured 45-second threshold is observed by a tick.
- Message cleanup runs after each handler, including errors. Connection cleanup
  removes backend listeners. Owned timers and room membership are released by
  the kernel on disconnect or worker drain. Timer callbacks capture values,
  not completed `Message` instances.
- The browser caps pending replies at 32, reply waits at eight seconds, outgoing
  buffered data at 64 KiB, and rendered log entries at 200. It reconnects at most
  eight times with jittered exponential backoff and restores acknowledged room
  and clock subscriptions. It closes sockets on `pagehide` for browser navigation.

Broadcasts are best-effort, in-memory delivery across one server's workers;
`chat.sent` acknowledges acceptance, not persistence or delivery to every peer.
For durable chat, add storage, deduplication, and replay cursors. Do not use
native descriptors as globally unique user IDs.

The demo's JWT session is stateless. Logout clears the cookie and the current
browser connection; it is not a distributed token-revocation system. A production
identity service should support revocation and closing a user's existing sockets.

## Configuration and deployment

| Variable                     | Default                                                         | Purpose                                                                  |
| ---------------------------- | --------------------------------------------------------------- | ------------------------------------------------------------------------ |
| `OKTAAX_PORT`, `OKTAAX_HOST` | `3000`, `127.0.0.1`                                             | Normal guide runner settings                                             |
| `OKTAAX_WS_WORKERS`          | `2`                                                             | Process workers, from 1 to 8                                             |
| `OKTAAX_WS_ORIGINS`          | Exact localhost and 127.0.0.1 origins using the configured port | Comma-separated HTTP(S) origins, without trailing slashes                |
| `OKTAAX_WS_SECRET`           | Random key generated before fork                                | At least 32 bytes; set a stable secret to retain sessions across restart |
| `OKTAAX_WS_SESSION_SECONDS`  | `1200`                                                          | Session lifetime, from 60 to 3600 seconds                                |
| `OKTAAX_WS_SECURE_COOKIE`    | `0`                                                             | Force Secure cookies behind an HTTPS terminator                          |
| `OKTAAX_WS_LOG`              | `0`                                                             | Log event/ID/timing metadata without message bodies or credentials       |

For another port:

```sh
OKTAAX_PORT=3001 OKTAAX_WS_LOG=1 php examples/guide/serve.php websocket-complete
```

Use WSS for remote deployments, configure exact public origins, forward Upgrade
headers through your proxy, and set proxy idle timeouts above heartbeat intervals.
For multiple server instances, add an external broker; worker IPC stays within
one native server. Limit connections and ingress at your proxy as appropriate.

WebSocket callbacks have an application scope, not an active HTTP request scope.
For database work, borrow a worker pool connection per message and release it in
`finally` or `Message::defer()`; avoid retaining it for the socket's lifetime.
The HTTP-scoped `Oktaax\Database\DB` bridge is not the connection scope for a
WebSocket message.

No test cases are added under `tests/`. The example can be checked by running the
browser walkthrough, PHP syntax checks, PHPStan, and Prettier.
