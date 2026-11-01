# Migration to the current API

[Guide index](README.md) · [API reference](api-reference.md)

The legacy compatibility layer has been removed. Old names do not alias to the
new implementation. Update imports, calls, startup registration, and assumptions
about global state before running an existing application.

## Namespace changes

| Removed namespace/type                                                          | Use instead                                                       |
| ------------------------------------------------------------------------------- | ----------------------------------------------------------------- |
| `Core\Application`                                                              | `Oktaax\Application` instance                                     |
| `Core\Configuration`                                                            | `$app->config()` / `Config\Configuration`                         |
| `Core\Container`                                                                | `$app->container()` / `Container\Container`                       |
| `Core\Context`                                                                  | `Application\Context` owned by `$app`                             |
| `Core\URL`, `Core\Worker`, `Core\TableRegistery`                                | `Application\ServerAddress`, `WorkerContext`, `TableRegistry`     |
| `Core\Dispatcher\ReturnDispatcher`                                              | `Http\ResponseEmitter`                                            |
| `Core\Dispatcher\ExceptionDispatcher`                                           | `Http\ExceptionHandler`                                           |
| `Core\Event` native adapters                                                    | `Runtime\Swoole\Listener`; worker lifecycle uses hooks            |
| `Core\Task`                                                                     | `Task`                                                            |
| `Http\Router`, `Http\Route`, `Http\CallStack`                                   | `Http\Routing\Router`, `Route`, `Pipeline`                        |
| `Http\Client`                                                                   | `HttpClient`                                                      |
| `Http\Middlewares`                                                              | `Http\Middleware`                                                 |
| `Http\Support\File`, `StreamedResponse`, `SSEResponse`, `Report`                | `Http\Response\File`, `StreamedResponse`, `SseResponse`, `Report` |
| `Http\Support\Validation`                                                       | `Validation\Validator`                                            |
| `Contracts\JsonScheme`                                                          | `Http\Response\JsonEnvelope`                                      |
| `Interfaces\View`, `Interfaces\Channel`                                         | `Contracts\View`; WebSocket channels are removed                  |
| `Views\PhpView`                                                                 | `View\PhpView`                                                    |
| `Error\…`                                                                       | Current exception classes under `Exception`                       |
| `CoroutineServer` and its trait                                                 | `run(new Runtime\Swoole\CoroutineRuntime())`                      |
| `Oktaa`, `ServerBag`, `ResponseJson`, `Injectable`, `MethodProxy`, `BodyParser` | Removed; use current Application/Request/Response APIs            |
| `Types\Laravel`, configuration DTOs, Laravel trait                              | Removed; plain arrays and native configuration                    |

Namespaces in the table are relative to `Oktaax` unless already fully qualified.
The removed top-level `src/Types` is unrelated to `src/Utils/Types`, whose type
guards still support `Struct`.

## State and bootstrap

Replace static `Application`, `Configuration`, `Container`, and router calls
with an explicit owning application. `Oktaax\Utils\app()` returns that active
`Application`; it no longer returns a facade or falls back to the last-created
application. Use `$app->within($callback)` outside a framework callback.

Register routes, middleware, service definitions, helper methods, and permanent
observers before `listen()`/`run()`. The worker-start callback is too late for
those definitions. Use `onWorkerStart()` for creating/resolving worker resources
and registering worker-local event listeners. The previous `WorkerStart` adapter
is no longer a bootstrap API.

`$app->after()` and `finally()` are removed. Use `afterRequest()` at bootstrap for
a persistent observer, `$request->defer()` for per-request cleanup, and
`$request->connection()->defer()` for resources surviving handler completion.

## HTTP behavior

- Replace static router inspection with `$app->router()->resolve()`.
- Use `handleRequest($request, $response)` for full kernel execution. The old
  application `handle`/`resolve` aliases are removed.
- Replace multi-argument `json($data, $message, $errors)` with `jsonEnvelope()`.
  `json($data)` always sends the supplied value directly.
- `all()` uses query/body/cookie precedence, includes JSON fields, and preserves
  present null values. Route parameters are accessed with `param()`.
- `body()` reads JSON according to Content-Type. Invalid JSON produces HTTP 400.
- `validate()` returns only keys named in rules and does not coerce values.
- Set response status/headers before sending the first bytes.
- Configure `app.chunk_size` instead of a static response chunk-size property.
- Use `$response->sse()`, return an SSE response value, or explicitly pass a
  response to `start()`. No ambient response fallback remains.
- Request `create/getInstance` and static request/response helper registration
  are removed. Helpers belong to `$app->inject(...)` before boot.

## Errors, tasks, and autoload

Debug pages follow `app.debug`, false by default. Logger middleware no longer
swallows exceptions and renders a separate always-visible diagnostic page.
Update custom templates to the [current variables](errors.md#replace-the-html-templates).

Task timeout raises `TaskTimeoutException`; an unhandled remote failure raises
`TaskFailedException`. Catch those rather than testing a false result. A timed-out
remote task can still finish; design side effects accordingly.

The `xrequest`, `xcsrf_token`, `xserver`, and `oktaa` helpers are removed. Use
handler arguments, the request's generated `_token`, explicit runtime selection,
and the current `oktaax()` helper. The internal `_emit` compatibility
alias was replaced by `MultiWorkerEventEmitter::emitLocal()`.

After updating imports, run `composer dump-autoload`. Tests are under
`autoload-dev`, so the `Tests` namespace is absent in production. Optimized and
classmap-authoritative production autoload do not restore removed aliases.
Use the [example integration tests](testing.md) to check the migrated behavior.

## WebSocket composition

Register `$app->websocket('/ws')` on `Application` or `Oktaax`. `ProcessRuntime`
selects its native WebSocket server automatically. Remove `HasWebsocket`,
anonymous subclasses, manual `table()` setup, and the `xsocket()` factory.
The old `Websocket\Client`, `Server`, `Event`, `Route`, table/overload helpers,
and the `Contracts\Channel` interface are removed without aliases.

| Previous API                               | Current API                                                       |
| ------------------------------------------ | ----------------------------------------------------------------- |
| `$app->ws('event', $handler)`              | `$app->websocket('/ws')->on('event', $handler)`                   |
| `(Server $server, Client $client)` handler | `(Connection $connection, Message $message)`                      |
| `gate()` after upgrade                     | `authorize()` before upgrade; `onOpen()` after acceptance         |
| `exit()`                                   | `onClose()`                                                       |
| Client properties and `info()`             | Persistent connection `get()`/`set()` and `request()`             |
| Nested `getData()['data']`                 | `$message->data()`                                                |
| `reply($value)` / `push()`                 | `$connection->send($event, $data, $optionalId): bool`             |
| Mutable `to()` / experimental channels     | Immutable `room()` / `target()->except()` targets                 |
| Native `tick()` / `after()`                | Connection-owned `tick()` / `after()` with automatic cancellation |
| Unstructured error strings                 | Structured `error` events and protocol close codes                |

Every outbound message now consistently contains `event` and `data`, even when
data is null. Optional string IDs support explicit correlation; handlers must
copy IDs into replies themselves. Update clients previously expecting arbitrary
raw replies. Unknown envelope keys and malformed/oversized messages are rejected.
The default codec supports JSON text; the old custom binary helper is removed.
Provide a `Websocket\Codec` to introduce an application-specific binary format.
Custom `ServerRuntime` implementations must now declare `supportsWebSockets()`;
return false for runtimes that do not attach a WebSocket transport.
See the [full WebSocket guide](websocket.md) for authorization, lifecycle, rooms,
error policy, and runtime limits.
