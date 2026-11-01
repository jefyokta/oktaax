# Runnable examples

[Project home](../README.md) · [User guide](../docs/README.md)

The maintained user-guide recipes are in `guide/`. Each recipe returns an
application factory; `guide/serve.php` loads Composer, selects a recipe, applies
host/port/debug configuration, and starts its runtime. You can copy a factory's
registration code into your own entrypoint. The root-level scripts are additional
standalone experiments and utility demonstrations.

## Run a recipe

From the repository root, with CLI Swoole enabled:

```sh
composer install
php examples/guide/serve.php http
```

Keep the server terminal open, run the listed curl requests in a second terminal,
and stop with Ctrl+C before changing recipes. Requests below use the default
`http://127.0.0.1:3000` address. Use a separate port for concurrent examples:

```sh
OKTAAX_PORT=3001 php examples/guide/serve.php responses
OKTAAX_RUNTIME=coroutine php examples/guide/serve.php http
```

See the [environment variable reference](../docs/configuration.md#environment-variables-in-the-example-runner).
The MySQL recipe uses an external database; the other recipes need no external service.

| Recipe argument      | File                                                   | Demonstrates                                                              | Runtime |
| -------------------- | ------------------------------------------------------ | ------------------------------------------------------------------------- | ------- |
| `http`               | [http.php](guide/http.php)                             | JSON API, validation, middleware, protected group                         | Either  |
| `responses`          | [responses.php](guide/responses.php)                   | Views, JSON, envelope, cookie, redirect, file, stream, custom result      | Either  |
| `lifecycle`          | [lifecycle.php](guide/lifecycle.php)                   | Lazy services, worker timer, per-request resource, disposal               | Either  |
| `database`           | [database.php](guide/database.php)                     | Eloquent model, SQLite, CRUD, transactions, request cleanup               | Either  |
| `mysql`              | [mysql.php](guide/mysql.php)                           | Eloquent, MySQL, bounded worker pool, exclusive request leases            | Either  |
| `sse`                | [sse.php](guide/sse.php)                               | Finite ticks, persistent subscription, publishing, cleanup                | Either  |
| `tasks`              | [tasks.php](guide/tasks.php)                           | Await, dispatch, remote failure, timeout                                  | Process |
| `events`             | [events.php](guide/events.php)                         | Events across two process workers                                         | Process |
| `websocket`          | [websocket.php](guide/websocket.php)                   | Endpoint composition, JSON echo/ping, rooms and browser client            | Process |
| `websocket-complete` | [websocket-complete.php](guide/websocket-complete.php) | Authenticated chat, all WS operations, browser client, codecs and cleanup | Process |
| `http-client`        | [http-client.php](guide/http-client.php)               | Local upstream, fetch, JSON reader, close                                 | Either  |
| `csrf`               | [csrf.php](guide/csrf.php)                             | Body token generation and rejection                                       | Either  |
| `errors`             | [errors.php](guide/errors.php)                         | HTTP error gallery, debug page, custom JSON error                         | Either  |
| `inertia`            | [inertia.php](guide/inertia.php)                       | Base HTML page and X-Inertia JSON payload                                 | Either  |

## HTTP API

```sh
php examples/guide/serve.php http
```

```sh
curl -i http://127.0.0.1:3000/health
curl -i http://127.0.0.1:3000/users/42
curl -i -X POST http://127.0.0.1:3000/users \
  -H 'Content-Type: application/json' \
  -d '{"name":"Ada","email":"ada@example.com","admin":true}'
curl -i -X POST http://127.0.0.1:3000/users \
  -H 'Content-Type: application/json' -d '{}'
curl -i http://127.0.0.1:3000/private
curl -i http://127.0.0.1:3000/private -H 'X-Demo-Token: demo'
```

Expected statuses: 200, 200, 201, 422, 401, 200. Every response has an
`x-request-id` header. The created user includes only `name` and `email`.
The fixed demo token illustrates middleware flow, not a deployable login system.

## Responses

```sh
php examples/guide/serve.php responses
```

```sh
curl 'http://127.0.0.1:3000/?name=Ada'
curl http://127.0.0.1:3000/json
curl http://127.0.0.1:3000/envelope
curl -i http://127.0.0.1:3000/custom
curl -i http://127.0.0.1:3000/cookie
curl -i http://127.0.0.1:3000/redirect
curl http://127.0.0.1:3000/download
curl -N http://127.0.0.1:3000/stream
curl -N http://127.0.0.1:3000/generator
```

The page greets Ada; JSON is raw; the envelope contains `message`, `error`, and
`data`. `/custom` returns HTTP 201 with `{"name":"Notebook"}`. `/cookie` sets an
HttpOnly cookie; `/redirect` returns 302 to `/json`. The file contains a greeting.
The finite stream prints first/second/third lines; the generator prints two lines.

## Lifecycle

```sh
php examples/guide/serve.php lifecycle
```

```sh
curl http://127.0.0.1:3000/
curl http://127.0.0.1:3000/
```

`completed_requests` increases after each handled request; `ticks` increases once
per second. The first response normally reports 0 completed requests. Each
request creates a temporary stream released by `request->defer()`. Ctrl+C drains
the worker timer, logs worker stop, disposes the metrics resource, and shuts down.
The counter is process-local and resets on restart.

## SQLite database

```sh
php examples/guide/database/setup.php
php examples/guide/serve.php database
```

Requires `pdo_sqlite` and the development dependencies installed by
`composer install`. This recipe saves notes to a local SQLite file using
`illuminate/database` and an Eloquent `Note` model, with a separate connection for each request.
See the [database recipe](guide/database/README.md) for CRUD requests,
configuration, connection ownership, and expected responses.

## MySQL with a connection pool

The [MySQL recipe](guide/mysql/README.md) includes local setup with Compose,
environment configuration, and CRUD requests. After starting MySQL and
exporting the documented `DB_*` variables:

```sh
php examples/guide/mysql/setup.php
php examples/guide/serve.php mysql
```

It reuses the SQLite example's Eloquent model and routes with a lazy worker
connection pool. Each request holds an exclusive connection; cleanup rolls
back unfinished transactions before returning it.

## SSE

```sh
php examples/guide/serve.php sse
```

```sh
curl -N http://127.0.0.1:3000/events
```

This prints a `ready` event and three `tick` events, then closes. For persistent
pub/sub, leave this running in a second terminal:

```sh
curl -N http://127.0.0.1:3000/subscribe
```

Publish from a third terminal:

```sh
curl -X POST http://127.0.0.1:3000/publish \
  -H 'Content-Type: application/json' -d '{"message":"Hello, stream!"}'
```

The subscriber receives a `message` event. Stop the subscriber with Ctrl+C; its
listener is released when disconnect is detected. The sample uses one worker.

## Tasks

```sh
php examples/guide/serve.php tasks
```

```sh
curl -X POST http://127.0.0.1:3000/uppercase \
  -H 'Content-Type: application/json' -d '{"text":"hello"}'
curl -i -X POST http://127.0.0.1:3000/queue \
  -H 'Content-Type: application/json' -d '{"text":"hello"}'
curl -i http://127.0.0.1:3000/timeout
curl -i http://127.0.0.1:3000/failure
```

Expected: `{"result":"HELLO"}`, HTTP 202, HTTP 504, HTTP 502. The timeout route
intentionally allows its remote task to finish after the waiter has timed out.
This is not a durable queue.

## Events

```sh
php examples/guide/serve.php events
```

```sh
curl http://127.0.0.1:3000/
curl -X POST http://127.0.0.1:3000/increment
curl http://127.0.0.1:3000/
```

Two workers register a local counter listener. Publishing increments the local
counter and forwards the event to peers. Each read reports its worker name;
peer delivery is asynchronous. Counters are not shared storage.

## WebSocket

For the full walkthrough, run `php examples/guide/serve.php websocket-complete`.
The [complete WebSocket recipe](guide/websocket-complete/README.md) covers
authentication, room permissions, middleware, correlation IDs, heartbeat, timers,
cleanup, backend events, multi-worker broadcasts, reconnect, and custom codecs.
The simple echo recipe below remains available.

```sh
php examples/guide/serve.php websocket
```

Open `http://127.0.0.1:3000/` in a browser. The page reports connection status and
lets you send a message. Expected reply:

```json
{ "event": "echo", "data": "Hello, Oktaax!" }
```

The [client source](guide/views/websocket.html) shows the standard browser
`WebSocket` API. Send `{"event":"ping"}` to receive `{"event":"pong","data":null}`.
The recipe registers `/socket` directly on an `Application`, with no trait or
manual table. `chat.join` accepts `{"room":"team"}`; `chat.send` accepts
`{"room":"team","text":"Hello"}` and publishes to connections joined to that
room. The local demo permits joining any room; add your room authorization in
your own handler.
The sample intentionally accepts local clients without authentication.

## HTTP client

```sh
php examples/guide/serve.php http-client
```

```sh
curl http://127.0.0.1:3000/
```

Expected:

```json
{ "upstream_status": 200, "message": "Hello from the local upstream" }
```

The route performs a coroutine request to this same server's `/upstream`, consumes
its JSON body, and closes the response. Keep the default loopback host for this
self-calling recipe.

## CSRF

```sh
php examples/guide/serve.php csrf
```

Get a token:

```sh
curl http://127.0.0.1:3000/token
```

Copy the returned token into `TOKEN_FROM_PREVIOUS_RESPONSE`:

```sh
curl -i -X POST http://127.0.0.1:3000/message \
  -H 'Content-Type: application/json' \
  -d '{"_token":"TOKEN_FROM_PREVIOUS_RESPONSE","message":"Hello"}'
curl -i -X POST http://127.0.0.1:3000/message \
  -H 'Content-Type: application/json' -d '{"message":"Hello"}'
```

The first returns HTTP 200 with `{"message":"Hello"}` when the token is valid;
the second is HTTP 403. Tokens expire after 300 seconds. The fallback key is only
for this local demo; supply `OKTAAX_APP_KEY` with a real secret for your own app.

## Error page gallery

```sh
OKTAAX_DEBUG=1 php examples/guide/serve.php errors
```

Open the root page for links to HTTP 400/403/404/405/429/500/503, an unhandled
exception, and a custom JSON conflict handler. `/exception` shows source/trace
only with debug enabled. Restart using `OKTAAX_DEBUG=0` to see generic production 500. See [template customization](../docs/errors.md#replace-the-html-templates).

## Inertia

```sh
php examples/guide/serve.php inertia
```

```sh
curl 'http://127.0.0.1:3000/?name=Ada'
curl -i 'http://127.0.0.1:3000/?name=Ada' -H 'X-Inertia: true'
```

The first is a base HTML document with an escaped `data-page`. The second is the
JSON page payload with component `Welcome`, props containing Ada, and an
`X-Inertia` response header. Frontend packages/build tooling are not included.

## Verify the examples

```sh
OKTAAX_INTEGRATION=1 vendor/bin/pest tests/Integration/GuideExamplesTest.php
```

The test suite uses ephemeral loopback ports, starts the real recipe factories,
and stops their processes. It checks HTTP responses, task timeout/failure, finite
SSE, local fetch, and debug rendering. The guide and runtime integration tests also
exercise the WebSocket wire protocol. Root-level exploratory scripts are not
part of this maintained recipe test set.
