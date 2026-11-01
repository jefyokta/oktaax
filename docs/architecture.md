# Application architecture

[User guide](README.md) · [Public API](api-reference.md) · [Migration](migration.md)

The application owns definitions. Workers own runtime resources. Requests own
handler resources. Connections own resources that survive the handler, including
SSE subscriptions. These lifetimes are independent.

## Module ownership

```text
src/
  Application.php                 Application composition and lifecycle API
  Application/                    Bootstrap, context, resource scopes, workers
  Config/                         Per-application configuration
  Container/                      Lazy application and worker service factories
  Http/
    HttpKernel.php                Request execution and guaranteed cleanup
    ExceptionHandler.php          Exception policy; defaults precede custom handlers
    ErrorPageRenderer.php         HTTP and debug pages; application template overrides
    ResponseEmitter.php           Return-value serialization and emission
    Routing/                      Router, route, pipeline, lifecycle-aware collector
    Middleware/                   Middleware namespace matches its directory
    Response/                     File, JSON envelope, SSE, streamed response values
    Request.php
    Response.php
  HttpClient/                     Outbound HTTP implementation
  Runtime/
    ServerRuntime.php             Runtime capability and lifecycle contract
    Swoole/
      ProcessRuntime.php          Process workers, task workers, worker messaging
      CoroutineRuntime.php        One worker in an owned or existing coroutine loop
      Listener/                   Native callback adapters
  Task/                           Dispatching, completion, per-worker pending waits
  Event/                          Application events and worker-local subscriptions
  Validation/                     Validator; custom rules are instance-owned
  View/                           Template rendering
  Websocket/                      Endpoints, kernel, codecs, connections, rooms and broadcasts
  Contracts/                      Shared interfaces
  Exception/                      Framework exception types
  Utils/                          Existing utility and Struct implementation
resources/views/                  Built-in error templates
```

`src/Types/` has been removed. Its Laravel adapter and configuration DTOs were
obsolete. `src/Utils/Types/` remains: its guards are used by `Struct` and are
unrelated to the removed directory.

## Bootstrap and worker lifecycle

1. Construct an application with its own configuration, container, router, kernel,
   and resource registries. Default exception policy is installed here, before
   user configuration. Constructing a runtime listener has no bootstrap effects.
2. Register routes, application services, worker factories, and lifecycle hooks.
3. `run($runtime)` validates capabilities and boots the application. Boot compiles
   routing data and freezes configuration and service definitions.
4. The runtime creates its native server and binds callbacks to this application.
5. Each worker starts its container scope, creates its `WorkerContext`, invokes
   worker-start hooks, and runs the optional startup callback.
6. Requests execute through the same kernel in both runtimes.
7. Worker exit drains pending waits, timers, and active streams. Worker stop invokes
   worker-stop hooks and deferred cleanup, then disposes worker services.
8. Application shutdown closes remaining application services and invokes shutdown
   hooks. Teardown is idempotent and attempts every cleanup callback. An original
   runtime failure is preserved if teardown also fails.

Native worker failure is reported through `onWorkerError($id, $pid, $code,
$signal, $application)`. A worker killed without executing PHP cleanup cannot run
its disposal callbacks; resources requiring recovery must also have an external
lease, expiry, or another recovery mechanism appropriate to that resource.

Process runtime owns native process events. Coroutine runtime manages SIGTERM and
SIGINT when it owns the event loop. When embedded in an existing coroutine loop,
the caller retains signal handling and calls `$app->stop()` explicitly.

### Service lifetimes

```php
$app->container()->worker(
    'cache-connection',
    fn() => new CacheConnection(),
    fn($connection) => $connection->close(),
);
$app->onWorkerStart(function ($worker, $application) {
    $connection = $application->container('cache-connection');
});
```

`CacheConnection` above represents an application-provided connection class.
Factories run on first resolution, never on registration. A worker factory cannot
be resolved before worker start. Worker restart produces a new instance.
Application singletons cannot resolve and retain worker services. Disposers run in
reverse creation order. Missing services throw `OutOfBoundsException`.

Use worker factories for native connections and pools. Shared-memory tables are
prepared explicitly before native workers start; their registry belongs to the
application. Registry and configuration access no longer use process-wide arrays.

## Request and connection lifecycle

`HttpKernel::handle(RequestContext)` receives the application, request, response,
and request scope explicitly. The current coroutine holds a separate context
namespace for each application. `within()` restores the previous active
application on exit, including nested calls and failures.

The kernel binds the validator, view, helpers, and response chunk size to the
request's application, then dispatches middleware and routing. The response emitter
normalizes values and streams data. Finally, all `afterRequest` observers and
request cleanup callbacks run, and the entire context namespace is removed.

Request cleanup is LIFO and runs once. A request-local closure is released after
cleanup; it is never appended to a permanent application registry.

A response can remain open after handler execution. Its `ConnectionScope` keeps
connection cleanup independent from request cleanup. Response completion, failed
writes, native disconnect, or worker teardown closes this scope once and cancels
its timers. Delayed callbacks execute within their owning application.

```php
$app->afterRequest(function ($request, $response) {
    // Handler execution has finished; an SSE connection can still be open.
});
$app->onResponseClosed(function ($request, $response, $event) {
    $aborted = $event->data['aborted'];
});
$app->get('/events', function ($request, $response) {
    $sse = $response->sse();
    $sse->on('start', fn() => $sse->send(['connected' => true]));
    // Register subscription cleanup with $request->connection()->defer(...).
    return $sse;
});
```

Register global `afterRequest` observers before boot. Use
`$request->defer(...)` for cleanup belonging to one request and
`$request->connection()->defer(...)` for cleanup belonging to an open connection.

## Tasks and worker events

Task dispatch requires an active worker and a runtime that supports task workers.
Awaited tasks register a correlation ID before dispatch so an early completion
cannot be lost. Every exit path forgets that ID, including dispatch failure,
timeout, remote failure, and worker cancellation. Late completions are ignored.
Timeout releases the local waiter; it does not cancel an already running remote
task. The task can still finish and invoke its completion hook, but cannot recreate
the timed-out pending waiter.
Pending state belongs to one application worker. A remote exception is represented
by `TaskFailedException::$remoteError`; arbitrary remote exception constructors
are not invoked in the requesting worker.

Event emitters are worker services. Their listeners are cleared at worker stop,
and local callbacks run within their application's context. IPC listeners deliver
received event values locally without broadcasting them again.

## Public API and migration

The compatibility layer has been removed. Use these module paths:

| Concern                                  | Public API                                     |
| ---------------------------------------- | ---------------------------------------------- |
| Application                              | `Oktaax\Application` or fluent `Oktaax\Oktaax` |
| Configuration and services               | `$app->config()` and `$app->container()`       |
| Routing                                  | `Oktaax\Http\Routing` and `$app->router()`     |
| Response values                          | `Oktaax\Http\Response`                         |
| Outbound HTTP                            | `Oktaax\HttpClient`                            |
| Tasks                                    | `Oktaax\Task`                                  |
| Native event adapters                    | `Oktaax\Runtime\Swoole\Listener`               |
| Templates, validation, contracts, errors | `View`, `Validation`, `Contracts`, `Exception` |

Old namespaces, aliases, static application/configuration/container facades,
`CoroutineServer`, and the Laravel adapter are no longer available. Select the
coroutine runtime with `$app->run(new CoroutineRuntime())`.

`Oktaax\Utils\app()` returns the current `Application` only inside an active
execution scope. Construction does not select a default application. Outside
requests and runtime callbacks, use `$app->within(fn() => ...)`; nested scopes
restore their previous application even when a callback throws. Request and
response helpers are bound explicitly by the kernel.

Configure routes, configuration values, service definitions, and permanent
observers before boot. Worker startup callbacks can register worker-local
exception/response handlers; request handlers cannot mutate those registries.
Use worker lifecycle hooks instead of constructing workers directly or overriding
native managed events.

Use `$response->json($data)` for raw JSON and `jsonEnvelope()` for envelopes.
Use `$response->sse()` for a bound SSE response, return `new SseResponse()` from
a route for automatic binding, or call `start($response)` explicitly. An unbound
`start()` throws; it does not look up an ambient response. `send()` returns a
write result, and invalid event fields or JSON encoding errors propagate.

Use `TaskTimeoutException` and `TaskFailedException` when handling task failures.
Tests autoload through `autoload-dev`; production autoload does not include the
`Tests` namespace.

## Verification

```sh
composer test
composer analyse -- --debug
composer test:integration
```

`--debug` makes PHPStan run without parallel worker sockets, useful in restricted
execution environments. Integration tests require permission to bind loopback
sockets and spawn temporary PHP processes. They cover process and coroutine HTTP
servers, SSE emission, task completion/failure/timeout, and the WebSocket
entry point. Each test shuts its server down and removes its temporary files.
