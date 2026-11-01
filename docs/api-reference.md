# Public API reference

[Guide index](README.md) · [Examples](../examples/README.md)

Namespaces below are relative to `Oktaax` unless fully qualified. This is a map
of the supported application-facing surfaces; runtime adapters and boot helpers
are implementation details. Method arguments are positional unless shown as
named arguments in the linked guides.

## Application and entrypoints

| API                                                                       | Purpose                                                                    |
| ------------------------------------------------------------------------- | -------------------------------------------------------------------------- |
| `new Application(array $config = [])`                                     | Own configuration, routing, services, workers, tasks, kernel               |
| `new Oktaax($config = [])`                                                | Application plus process-server convenience methods                        |
| `config($key = null, $default = null)`                                    | Configuration object or configured value                                   |
| `container($id = null)`                                                   | Container object or resolved service                                       |
| `router()`, `context()`, `kernel()`                                       | Owned router, coroutine request context, HTTP kernel                       |
| `tasks()`, `workers()`, `tables()`                                        | Task dispatcher, worker lifecycle, table registry                          |
| `address()`, `nativeServer()`, `runtime()`, `state()`                     | Runtime/address metadata; nullable native server/runtime before attachment |
| `boot()`, `run(ServerRuntime $runtime)`, `stop()`, `shutdown()`           | Lifecycle; `run` is single-use                                             |
| `within(callable $callback)`                                              | Run in the active application scope and restore its predecessor            |
| `handleRequest(Request $request, Response $response)`                     | Full kernel execution; active coroutine required                           |
| `getRequest()`, `getResponse()`                                           | Current coroutine's request/response, or null                              |
| `get/post/put/patch/delete/head/options($path, $handler, ...$middleware)` | Register routes                                                            |
| `use(...$middleware)`                                                     | Global middleware for subsequently registered routes                       |
| `middleware($middleware, $register)`                                      | Scoped registration callback; no automatic prefix                          |
| `catch($type, Closure $handler)`                                          | Exception response policy                                                  |
| `respond($type, Closure $handler)`                                        | Custom returned-object response policy                                     |
| `inject($requestOrResponseClass, $name, $helper)`                         | Register a callable helper before boot                                     |
| `setView(Contracts\View $view)`                                           | Register the application view engine before boot                           |

Permanent observer methods: `afterRequest`, `onResponseClosed`, `onWorkerStart`,
`onWorkerStop`, `onWorkerError`, `onShutdown`. See callback signatures in
[lifecycle](lifecycle.md#lifecycle-hooks).

`Oktaax` additionally provides `listen($port, $hostOrCallback = null, $callback =
null)`, `setServer($keyOrMap, $value = null)`, `withSSL($certificate, $key)`,
`useCsrf($key, $expiry = 300)`, `on($nativeEvent, $handler)`, and `reload()`.
Untyped/string startup callback arguments receive the HTTP URL; typed arguments
can request the application, native server, `WorkerContext`, or `ServerAddress`.
Prefer lifecycle hooks for new application code.

## Optional database integration

Install `illuminate/database` to use `Database\Eloquent\Model` as your model
base. Register a request connection factory with `Database\DB::register($app,
$factory)` before boot. `DB::connection()` resolves the request's default
connection; `DB::transaction($callback, $attempts = 1)` preserves the callback's
return type. Models expose normal Eloquent query and persistence methods.
See [database scopes and examples](database.md).

## Configuration, container, scopes

| Type                          | Main API                                                                                                                                               |
| ----------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `Config\Configuration`        | `get`, `set`, `has`, `all`, `isFrozen`                                                                                                                 |
| `Container\Container`         | `singleton($id, $objectOrClosure, $dispose = null)`, `register($id, $object)`, `worker($id, Closure $factory, $dispose = null)`, `alias`, `get`, `has` |
| `Application\WorkerContext`   | `id`, `pid`, `name`, `taskWorker`, `isTaskWorker`, `isSelf`, `tick`, `send`, `workers`, `taskWorkers`, `others`, `onMessage` callback                  |
| `Application\WorkerLifecycle` | Application-facing `defer(Closure $cleanup)` for an active worker; native start/drain/stop are runtime-owned                                           |
| `Application\RequestScope`    | `defer(Closure $cleanup)`; kernel owns close                                                                                                           |
| `Application\ConnectionScope` | `defer`, `onClose`, `onDisconnect`, `isClosed`; response/runtime own close                                                                             |
| `Application\TableRegistry`   | `create($name, Swoole\Table $table)`, `get`, `preventRecreate`                                                                                         |
| `Application\Context`         | `get`, `set`, `has`, `remove`, `request`, `response`; kernel owns destroy                                                                              |
| `Application\ServerAddress`   | `getHttpUrl()`, `getWebsocketUrl()`                                                                                                                    |

Built-in service IDs: `Contracts\View` (`'view'`), `Validation\Validator`,
`Http\ErrorPageRenderer`, and worker-scoped `Event\EventEmitter` (`'event'`).
`Application::$worker` is populated by the runtime and null outside worker scope.
Container `instance()` is intended for runtime-owned objects, not late application
service registration.

## HTTP request

Full semantics: [requests and validation](requests.md).

| Category      | API                                                                                                                                                                         |
| ------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Input         | `query/get`, `body`, `json`, `cookie`, `param`, `input`, `all`, `parameters`, `has`                                                                                         |
| Validation    | `validate($rules, $data = null)`                                                                                                                                            |
| Metadata      | `header`, `hasHeader`, `getMethod`, `isMethod`, `path`, `host`, `userAgent`, `protocol`, `isJson`, `isFormSubmission`, `wantsJson`, `wantsJS`, `isInertia`, `isAjax`, `xhr` |
| Native access | `$request->request`, `server`, `files`, `headers`, `fd`, route `params`                                                                                                     |
| Lifetime      | `defer`, `scope`, `connection`, `onClose`, `onClientDisconnected`                                                                                                           |
| Custom state  | Request-local property assignment and registered helper methods                                                                                                             |

Prefer explicit source accessors over aliases such as `post`, `bodies`, or
invoking the request object as a function. Input keys are literal; see precedence
and null handling in the request guide.

## HTTP response

Full semantics: [responses](responses.md), [streaming](streaming.md), [errors](errors.md).

| Category                 | API                                                                                          |
| ------------------------ | -------------------------------------------------------------------------------------------- |
| Metadata                 | `status`, `header`, `type`, `cookie`, `sendHeaders`, `hasStarted`, `getStatus`, `getHeaders` |
| Bodies                   | `end`, `write`, `json`, `jsonEnvelope`, `render`, `sendfile`                                 |
| Values                   | `stream(Closure $writer, $status = 200, $headers = [])`, `sse($headers = [])`                |
| Navigation               | `redirect`, `back`; `with` and `withError` set short-lived message cookies                   |
| Errors                   | `renderHttpError($status)`, `renderException(Throwable $error)`                              |
| Lifetime                 | `connection`, `onResponseClosed`, `isWritable`, `isEnded`, `preventEnd`, `isEndPrevented`    |
| Inspection/native access | `getContent`, `swoole`, `getSwooleResponse`                                                  |

`useHelpers`, `useView`, `useErrorPages`, and `setChunkSize` are kernel-binding
operations. Normal handlers do not need to call them. Native coroutine WebSocket
forwarders (`upgrade`, `recv`, `push`, `close`) depend on the underlying response;
the supported WebSocket module uses registered endpoints and ProcessRuntime.

Response value classes live under `Http\Response`: `File`, `JsonEnvelope`,
`StreamedResponse`, `SseResponse`. `Report` is an invocation helper returning the
active response's status, final `end()` payload, and headers; invoke it in an
active request observer. It does not capture an entire streaming transcript.

## Routing and error internals

`Http\Routing\Router::resolve($path, $method)` returns a handler/parameter tuple.
`dispatch($request, $response)` executes routing without the full kernel.
`collector()` is a low-level FastRoute-compatible collector; its mutations
invalidate compiled data and are rejected after boot. `compile()` caches the
native dispatcher. Prefer application route registration for normal use.

`Http\ErrorPageRenderer` accepts optional absolute HTTP/debug template paths and
renders `http($status, Request)` or `exception(Throwable, Request)`. The response
wrapper decides whether debug rendering is permitted. See [template variables](errors.md#replace-the-html-templates).

## Tasks, events, WebSocket, client

- `Task\Taskable::handle()` is a no-argument task operation. Optional contracts:
  `Completable::onComplete` and `Catchable::catch`.
- `$app->tasks()->dispatch($class, $payload)` and `await($class, $payload,
$timeout = -1)` perform task-worker work. [Task guide](tasks-events.md).
- `Event\EventEmitter`: `on`, `once`, `off`, `emit`, `clear`, `listenerCount`,
  `hasListeners`. `Event` has `name`, `data`, and optional `sender` metadata.
- `$app->websocket($path)` returns `Websocket\Endpoint`; `$app->websockets()` is
  the owned kernel. Endpoint methods: `on`, `authorize`, `origins`, `use`,
  `onOpen`, `onClose`, `onError`, `maxMessageBytes`, `codec`, `broadcast`, `room`,
  and `target`. `Websocket\Connection` provides `id`, `request`, `endpoint`,
  `get/set`, `send`, `close`, `isOpen/isClosed`, `defer`, `tick/after`,
  `cancelTimer`, `join/leave/inRoom/rooms`. `Websocket\Message` provides
  `event/data/id`, `validate`, and `defer`. See the [WebSocket guide](websocket.md).
- `HttpClient\RequestOptions`, `HttpClient\Response`, `Http\Headers`, and
  `Utils\fetch`: see the [HTTP client guide](http-client.md).
- `Console`, `Utils\Struct`, `_o`, `getter`, `Boolean`, and `Utils\Invoker`:
  [utility guide](utilities.md).

## Exception types

| Exception                | Meaning                                                            |
| ------------------------ | ------------------------------------------------------------------ |
| `HttpException`          | Status and headers for an HTTP error                               |
| `ValidationException`    | Field/rule error map, available through `getErrors()`              |
| `TaskTimeoutException`   | Local task wait expired; remote task may continue                  |
| `TaskFailedException`    | Unhandled remote failure, in `remoteError`                         |
| `CleanupException`       | Multiple cleanup callbacks failed; inspect its `errors`            |
| `EventDispatchException` | One or more event listeners failed; call its `exceptions()` method |
| `ViewNotFound`           | A requested PHP template was unavailable                           |
| `ClientClosed`           | Transport can no longer receive the response                       |

`Websocket\ProtocolException` describes rejected WS application framing and
provides its protocol close code through `closeCode`.

Standard PHP exceptions also report registration, lifecycle, type, or missing
service errors. Catch narrowly where the application can recover.
