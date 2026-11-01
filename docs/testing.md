# Testing and troubleshooting

[Guide index](README.md) · [Examples](../examples/README.md)

## Run the repository checks

Install formatter tooling with `npm ci`, run `npm run format` to format source,
templates, examples, and documentation, or use `npm run format:check` to verify
formatting without writing files. PHP formatting uses `@prettier/plugin-php`.

```sh
composer install
composer test
composer analyse -- --debug
composer test:integration
composer validate --no-check-publish
```

The current development dependencies require PHP 8.4+. `composer test` skips
socket integration tests unless `OKTAAX_INTEGRATION=1`. The integration command
enables them and tests both runtimes and the runnable guide factories. It needs
`proc_open`, loopback socket binding, and permission to create temporary files
and PHP processes. Each server is stopped after the test.

PHPStan is configured at level 1 for the main application/runtime/HTTP modules.
`--debug` avoids PHPStan's parallel worker sockets in restricted environments.
Passing this check is not a higher-level type coverage guarantee for every utility
and experimental module.

## Test application behavior without a listening socket

When working in this repository, the test support classes let you run the actual
kernel in a coroutine:

```php
use Oktaax\Application;
use Tests\Support\Coroutines;
use Tests\Support\HttpFixture;

it('returns a filtered user', function () {
    Coroutines::run(function () {
        $app = new Application();
        $app->post(
            '/users',
            fn($request) => $request->validate(['name' => 'required|string']),
        );
        $http = new HttpFixture(
            uri: '/users',
            method: 'POST',
            headers: ['content-type' => 'application/json'],
            body: '{"name":"Ada","admin":true}',
        );
        $app->handleRequest($http->request, $http->response);
        expect($http->nativeResponse->sentStatus)->toBe(200);
        expect(json_decode($http->nativeResponse->sentBody, true))->toBe([
            'name' => 'Ada',
        ]);
    });
});
```

`Tests\Support` belongs to this checkout's `autoload-dev`, not the production
library API. In your own application, provide native request/response doubles or
start an ephemeral server and exercise HTTP. `handleRequest()` needs an active
coroutine because request context is coroutine-local.

Testing only `router()->resolve()` checks path/method matching. It does not verify
serialization, exception responses, helpers, service binding, or cleanup. Test the
full kernel for those behaviors, and use a real server for wire-level behavior.

## Validate production autoload

```sh
composer dump-autoload --no-dev --classmap-authoritative
php -r 'require "vendor/autoload.php"; $app = new Oktaax\Application(); echo $app->state(), PHP_EOL;'
composer dump-autoload --dev
```

Expected output is `configuring`. The final command restores development
autoloading; run it before using this repository's test support classes.

## Common problems

| Symptom                                                 | Cause and next step                                                                                               |
| ------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| `Class Swoole… not found`                               | Verify `php --ri swoole` using the exact CLI binary that starts the server                                        |
| Composer platform error                                 | Check PHP version and installed extensions; development dependencies require a newer PHP than the library minimum |
| Address already in use                                  | Stop the previous process or change `OKTAAX_PORT` in the example runner                                           |
| Config/routes/services are frozen                       | Move registration before `run()`/`listen()`, outside request/worker-start callbacks                               |
| No application is active                                | Pass the application explicitly or use `$app->within(...)`; construction does not choose a global app             |
| Request context requires a coroutine                    | Enter `Swoole\Coroutine\run()` in a direct-kernel test                                                            |
| Worker service unavailable                              | Resolve it inside worker/request execution, not while defining the app                                            |
| Application singleton cannot retain a worker dependency | Make the owner worker-scoped too, or pass the worker service per operation                                        |
| Unexpected 404/405                                      | Check the full route path and method; group middleware does not add a prefix                                      |
| Handler result disappears                               | Return `$next()` from middleware                                                                                  |
| Validation excludes fields                              | Only ruled keys are returned; explicitly validate all permitted fields                                            |
| JSON body empty or malformed                            | Set Content-Type, send the complete valid JSON value, inspect `body()` vs `query()`                               |
| Header/status mutation throws                           | Set metadata before the first write/end/file send                                                                 |
| SSE closes early or never updates                       | Return the SSE value, use connection cleanup, and check proxy buffering/timeouts                                  |
| SSE `start()` needs a response                          | Use `$response->sse()`, return the value, or pass `start($response)`                                              |
| Task workers unavailable                                | Use process runtime, set `task_worker_num`, and dispatch only in a running worker                                 |
| HTTP client body already used                           | Call one body reader once; preserve its returned value if needed again                                            |
| Inertia view is missing                                 | Set `inertia.base_view` and the view engine before boot                                                           |
| No detailed exception page                              | Enable `app.debug` locally; generic 500 is the production default                                                 |
| Old namespace cannot autoload                           | Follow the [migration table](migration.md); aliases were removed                                                  |

## Performance and operations checks

Measure your own handlers and I/O under the chosen runtime. Register persistent
hooks once, avoid retaining request objects in singleton/worker services, and use
bounded producers for streams. Close unused outbound HTTP responses. A PHP array
in a worker is not a distributed cache; task dispatch and IPC are not persistent
queues. For a restart/shutdown check, run the lifecycle example and confirm it
exits cleanly with Ctrl+C, then verify fresh counters after restart.
