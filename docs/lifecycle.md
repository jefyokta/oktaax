# Application lifecycle and services

[Guide index](README.md) · [Runnable recipe](../examples/guide/lifecycle.php) ·
[Internal ownership](architecture.md)

## Four lifetimes

| Owner       | Typical data                                                   | Cleanup API                                                             |
| ----------- | -------------------------------------------------------------- | ----------------------------------------------------------------------- |
| Application | Configuration, routes, service definitions, immutable settings | `onShutdown()`; singleton disposer                                      |
| Worker      | Database connections, pools, local event listeners, timers     | `onWorkerStop()`; worker disposer; `$app->workers()->defer()`           |
| Request     | Handler resources and temporary state                          | `$request->defer()`                                                     |
| Connection  | SSE/WS subscriptions, state, and timers                        | HTTP/SSE: `$request->connection()->defer()`; WS: `$connection->defer()` |

A PHP worker handles many requests. Do not store a current user, request, or
response in a singleton, a static property, or a long-lived worker closure.
Application-scoped objects are not automatically shared across process workers.
For shared data use a suitable database, cache, or an explicitly prepared
`Swoole\Table`.

## Configure, boot, run, stop

States are `configuring`, `booted`, `running`, `stopping`, and `stopped`.
`run($runtime)` validates runtime capabilities, calls `boot()`, starts the runtime,
and performs shutdown when it returns or fails. `run()` is single-use.
`Oktaax::listen()` configures the address and starts `ProcessRuntime`.

Boot compiles/freezes routing, configuration, container definitions, helper
registration, and permanent lifecycle observers. Register them before `run()` or
`listen()`, not from a request or a worker-start callback. Exception/result
handlers can additionally be registered at worker bootstrap; request handlers
cannot register them through the application API.

`stop()` asks a running runtime to stop; shutdown disposes remaining resources.
`shutdown()` is idempotent. Do not call the runtime-owned `attachServer`, worker
start, or worker stop methods in application handlers.

## Lazy service factories

```php
$app->container()->singleton('settings', (object) ['currency' => 'IDR']);
$app->container()->worker(
    'connection',
    fn() => new PDO(getenv('DB_DSN'), getenv('DB_USER'), getenv('DB_PASSWORD')),
    function ($connection) {
        /* Close custom resource wrappers here if needed. */
    },
);
$app->container()->alias('db', 'connection');
```

The PDO example needs your own database and driver. The self-contained lifecycle
recipe uses an in-memory resource instead. Factories receive the container as
an argument when needed. `singleton()` also accepts a prebuilt object;
`register($id, $object)` is equivalent to registering a singleton.

Resolve through `$app->container('db')` or `$app->container()->get('db')`.
Factories run once on first resolution in their scope. Worker resources cannot
resolve before worker start. A worker restart creates a new resource. A singleton
factory cannot resolve a worker-scoped dependency. Missing services, circular
dependencies, and factories returning the wrong class/interface raise errors.
There is no automatic class construction for unregistered services.

Disposers run in reverse creation order. Worker-stop hooks execute before worker
service disposal, so their services are still available. Cleanup attempts all
callbacks; a single error is rethrown, multiple errors produce `CleanupException`.
Application services are disposed during application shutdown.

## Lifecycle hooks

| Registration                  | Callback arguments                      | Meaning                                             |
| ----------------------------- | --------------------------------------- | --------------------------------------------------- |
| `onWorkerStart($callback)`    | `WorkerContext`, `Application`          | Worker scope is ready                               |
| `onWorkerStop($callback)`     | `WorkerContext`, `Application`          | Before worker services are disposed                 |
| `onWorkerError($callback)`    | ID, PID, exit code, signal, application | Native process worker failure                       |
| `afterRequest($callback)`     | Request, response                       | Handler/emission finished; stream may still be open |
| `onResponseClosed($callback)` | Request, response, close event          | Connection ended or aborted                         |
| `onShutdown($callback)`       | Application                             | Application teardown                                |

```php
$app->onWorkerStart(function ($worker, $app) {
    if ($worker->isTaskWorker()) {
        return;
    }
    $resource = $app->container('connection');
    $app->workers()->defer(function () {
        /* Additional worker-local cleanup. */
    });
});
$app->afterRequest(function ($request, $response) {
    error_log(
        $request->getMethod() .
            ' ' .
            $request->path() .
            ' ' .
            $response->getStatus(),
    );
});
```

Hooks apply to task workers too; check `isTaskWorker()` when an action is only for
HTTP workers. A process forcibly killed without running PHP cleanup cannot run
its hooks. Do not depend on these hooks as the sole persistence mechanism.

## Request and connection cleanup

```php
$app->get('/report', function ($request) {
    $handle = fopen('php://temp', 'w+');
    if ($handle === false) {
        throw new RuntimeException('Cannot open temporary stream');
    }
    $request->defer(static fn() => fclose($handle));
    return ['ready' => true];
});
```

Request cleanup runs once in reverse registration order, even when a handler or
observer throws. All application-specific request context is then destroyed.
A per-request cleanup closure is released after completion, rather than appended
to the permanent application's hook registry.

An SSE handler returns while its response remains open. Put its unsubscribe and
timer cleanup in `connection()->defer()`, not `request->defer()`. Close events
carry `data['aborted']` and `data['disconnected']`.
`onClientDisconnected($callback)` observes native disconnection, not every normal
response completion. See [streaming](streaming.md).

## The active application

```php
use function Oktaax\Utils\app;

$app->within(function () use ($app) {
    assert(app() === $app);
});
```

`app()` returns the actual active `Application` during requests, runtime
callbacks, and framework-managed timer/connection callbacks. Outside a scope it
throws `LogicException`. Constructing another application does not select a
global default. `within()` restores a previous nested scope, including failures.
An arbitrary new coroutine is not promised to inherit your application's scope;
wrap its callback with `$app->within(...)` or capture/pass the application.

`getRequest()` and `getResponse()` are nullable and valid only for the current
request's coroutine. A connection callback after handler cleanup must capture the
needed connection objects; looking up the previous request through `app()` will
not restore it.

## Shared-memory tables

Create table columns and call the native table's `create()` before starting
process workers, then register it with `$app->tables()->create('name', $table)`.
Retrieve it with `tables()->get('name')`. This registry stores supplied tables; it
does not create native memory or columns. Repeated names retain the first table
unless `preventRecreate(false)` is selected. WebSocket endpoints manage their own worker-local connection registry and
forward broadcasts through IPC; they do not require a manually supplied table.

## WebSocket connections

An endpoint's `onOpen`/`onClose` hooks describe a WebSocket connection, separate
from HTTP `afterRequest`/`onResponseClosed` observers. A persistent `Connection`
owns attributes, room membership, subscriptions, and cancellable timers. Each
`Message` owns its temporary cleanup via `defer()`, even when its handler fails.
Use `Connection::request()` for handshake metadata; a WS message does not become
the application's current HTTP request. See [WebSocket lifecycle](websocket.md#connection-identity-and-hooks).
