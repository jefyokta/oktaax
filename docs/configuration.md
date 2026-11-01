# Configuration and operations

[Guide index](README.md) · [Lifecycle](lifecycle.md)

## Defaults and configuration API

```php
$app = new \Oktaax\Application([
    'app' => ['name' => 'My service', 'host' => '127.0.0.1', 'port' => 3000],
    'server' => ['worker_num' => 2],
]);
$app->config()->set('app.chunk_size', 16384);
$name = $app->config('app.name', 'Fallback');
```

Constructor overrides are merged recursively with defaults. `config()` returns
the application's `Config\Configuration`; `config($key, $default)` reads a
value. Dot-separated keys select nested arrays. `has()`, `all()`, and `isFrozen()`
are also available. Explicit null values are preserved. Mutation after boot throws.

| Key                            | Default           | Purpose                                                |
| ------------------------------ | ----------------- | ------------------------------------------------------ |
| `app.host`                     | `127.0.0.1`       | Bind address                                           |
| `app.port`                     | `3000`            | Listen port                                            |
| `app.protocol`                 | `http`            | Address scheme; coroutine TLS selection                |
| `app.name`                     | `Oktaax`          | Application metadata                                   |
| `app.debug`                    | `false`           | Exception diagnostics                                  |
| `app.chunk_size`               | `8192`            | Positive body chunk size in bytes                      |
| `app.error_views.http`         | unset             | Optional absolute HTTP error template                  |
| `app.error_views.exception`    | unset             | Optional absolute debug exception template             |
| `server.worker_num`            | `1`               | Process HTTP workers; must be 1 for coroutine runtime  |
| `server.hook_flags`            | `SWOOLE_HOOK_ALL` | Native runtime hook setting                            |
| `server.task_worker_num`       | unset/0           | Optional process task workers                          |
| `server.task_enable_coroutine` | unset             | Enable coroutine execution in task workers when needed |
| `inertia.base_view`            | unset             | Base view for the optional Inertia helper              |

Other `server` keys are passed to native Swoole settings. Process mode/socket type
are read separately from `server.mode` and `server.sock_type`; coroutine runtime
removes unsupported process settings before configuring its native server.
WebSocket endpoints require `server.dispatch_mode=2`; the runtime sets it when
unset and rejects a different value to keep each connection on its owning worker.
`Oktaax::setServer($key, $value)` or `setServer($map)` writes these settings before
boot. There is no automatic `.env` file loader.

## Choose a runtime

| Capability                       | `ProcessRuntime`                         | `CoroutineRuntime`                            |
| -------------------------------- | ---------------------------------------- | --------------------------------------------- |
| Shared HTTP kernel and route API | Yes                                      | Yes                                           |
| Number of HTTP workers           | Configurable                             | Exactly 1                                     |
| Task workers                     | Yes, when configured                     | Rejected                                      |
| Worker IPC / cross-worker events | Yes                                      | Rejected                                      |
| SSE and streamed HTTP            | Yes                                      | Yes                                           |
| WebSocket endpoints              | Automatic native server selection        | Rejected before boot                          |
| Existing coroutine event loop    | Native process server owns its lifecycle | Can join the caller's loop                    |
| Signals                          | Native Swoole process lifecycle          | SIGINT/SIGTERM installed when owning the loop |

When coroutine runtime is embedded in an existing loop, the caller owns signal
handling and calls `$app->stop()`. An application/runtime is single-use. For
process-server extension, use lifecycle hooks rather than overriding managed
native events. `Oktaax::on()` only allows unmanaged native events.

## Environment variables in the example runner

| Variable             | Default                                                                          | Applies to                                                |
| -------------------- | -------------------------------------------------------------------------------- | --------------------------------------------------------- |
| `OKTAAX_HOST`        | `127.0.0.1`                                                                      | Guide runner bind address                                 |
| `OKTAAX_PORT`        | `3000`                                                                           | Guide runner listen port, 1–65535                         |
| `OKTAAX_RUNTIME`     | `process`                                                                        | `process` or `coroutine`                                  |
| `OKTAAX_DEBUG`       | `0`                                                                              | Debug diagnostics; `1` enables                            |
| `OKTAAX_APP_KEY`     | Development-only example key                                                     | CSRF recipe                                               |
| `OKTAAX_SQLITE_PATH` | `examples/guide/database/database.sqlite` (absolute path resolved by the recipe) | Database setup and recipe; override with an absolute path |

These are conventions implemented by `examples/guide/serve.php`, not automatic
framework configuration. Map your own environment variables explicitly in your
application's config. The tasks, events, and WebSocket recipes require process
runtime. A host of `0.0.0.0` binds all interfaces; use a concrete reachable host in
clients and upstream URLs. The self-calling HTTP-client recipe uses loopback by
default.

The MySQL recipe also reads `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`,
`DB_PASSWORD`, `DB_POOL_SIZE`, `DB_POOL_TIMEOUT`, and `DB_CONNECT_TIMEOUT`.
See its [configuration and pool guide](../examples/guide/mysql/README.md#environment-variables).

The complete WebSocket recipe has its own [worker, origin, session, and logging
configuration](../examples/guide/websocket-complete/README.md#configuration-and-deployment).

## TLS and reverse proxies

The process convenience entrypoint supports:

```php
$app = new \Oktaax\Oktaax();
$app->withSSL('/absolute/path/cert.pem', '/absolute/path/key.pem');
$app->get('/health', fn() => ['ok' => true]);
$app->listen(3443, '127.0.0.1');
```

Files must exist and the native extension must support SSL. A reverse proxy can
terminate TLS instead and forward HTTP to a local bind address. Configure the
proxy to preserve host information, allow WebSocket upgrades for socket traffic,
and disable buffering/use appropriate read timeouts for SSE. The SSE response
already sends `X-Accel-Buffering: no`; verify the proxy honors it.

The framework does not provide a trusted-proxy configuration layer, automatic
forwarded-header normalization, an HTTP session store, a static-asset pipeline,
or built-in rate limiting. Implement those concerns in your application/proxy.
Do not infer the original client's IP or HTTPS scheme from arbitrary forwarded
headers without your own trust boundary.

## Run and stop a service

Run the PHP entrypoint as a foreground process managed by your chosen service
manager. Set a working directory, explicit CLI PHP binary, environment, logging,
and restart policy. Use `composer install --no-dev --optimize-autoloader` for a
production dependency set and keep `app.debug` false. Composer authoritative
class maps are supported by the current namespaces.

Restart or reload workers after source/config changes; no development file
watcher is included. `Oktaax::reload()` asks an already-started native server to
reload. Worker services are recreated on restart; PHP arrays and worker-local
counters are not durable storage.

During process exit, the framework drains pending waits, owned timers, and open
stream scopes on the native `WorkerExit` event, then runs worker-stop hooks and
service disposal on `WorkerStop`. This lets recurring timers stop before the
native event loop waits to exit. Swoole's own
[worker-exit helper](https://github.com/swoole/library/blob/master/src/core/Server/Helper.php)
also releases its timer at that phase. Coroutine runtime performs the same owned
resource cleanup as it stops its server.

Do not assume forced termination runs disposal hooks. Keep cleanup bounded and
choose a service-manager stop timeout that allows active work to finish. Use the
[real lifecycle example](../examples/guide/lifecycle.php) to inspect start/stop
behavior in your deployment environment.
