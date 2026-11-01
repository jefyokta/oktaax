# Getting started

[Guide index](README.md) · Next: [Routing](routing.md)

## Install and check the environment

The library declares PHP 8.3+ and `ext-swoole`. Development dependencies in this
checkout require PHP 8.4+. Use the same CLI PHP binary for Composer and the server.

```sh
php -v
php --ri swoole
composer require jefyokta/oktaax
composer check-platform-reqs
```

If `php --ri swoole` says the extension is missing, install and enable Swoole for
that CLI PHP installation before continuing. OpenSwoole's different namespace is
not an automatic substitute for the `Swoole` classes used by this library.

For this repository itself, use `composer install` instead of `composer require`.
The examples are available in a source checkout; Composer consumers can copy a
recipe into their own project and adjust the autoload path.

## Run a complete example

From the repository root:

```sh
php examples/guide/serve.php http
```

It prints the HTTP address once a worker starts. In another terminal:

```sh
curl -i http://127.0.0.1:3000/health
curl -i http://127.0.0.1:3000/users/42
curl -i -X POST http://127.0.0.1:3000/users \
  -H 'Content-Type: application/json' \
  -d '{"name":"Ada","email":"ada@example.com","admin":true}'
```

Expected results: HTTP 200 with `{"ok":true}`; HTTP 200 with `{"id":"42"}`;
HTTP 201 with only `name` and `email`. The unvalidated `admin` field is excluded.
A nonnumeric user path is 404; POST to `/health` is 405 with an `Allow` header.

Stop the process with Ctrl+C before starting another recipe on port 3000.
To select another port:

```sh
OKTAAX_PORT=3001 php examples/guide/serve.php responses
```

## Write your own entrypoint

The [project README](../README.md#your-first-server) contains a complete
`server.php`. Always load Composer using a path relative to the entrypoint,
register routes, then start the server. Oktaax is a long-running CLI server; do
not launch it through PHP-FPM or `php -S`.

`new Oktaax()` plus `listen()` uses the process runtime. For explicit composition:

```php
use Oktaax\Application;
use Oktaax\Runtime\Swoole\CoroutineRuntime;

$app = new Application(['app' => ['host' => '127.0.0.1', 'port' => 3000]]);
$app->get('/health', fn() => ['ok' => true]);
$app->run(new CoroutineRuntime());
```

`ProcessRuntime` supports multiple HTTP workers, task workers, and IPC.
`CoroutineRuntime` supports one worker and can join an existing coroutine loop.
See the [runtime capability table](configuration.md#choose-a-runtime).

## Suggested application layout

This is a layout for your own application, separate from the library's `src/`:

```text
my-service/
  composer.json
  server.php                  Load autoload, configuration, services, routes; run
  config/app.php              Return plain configuration arrays
  src/
    Http/Handlers/             Invokable handlers or controller objects
    Http/Middleware/           Application middleware
    Services/                  Domain services and worker resources
    Tasks/                     Taskable implementations
  routes/http.php              Register routes on the supplied application
  views/                      PHP templates
  tests/                      Application tests
```

Give your application's namespace its own Composer PSR-4 mapping. A route file
can return a registration closure:

```php
// routes/http.php
use Oktaax\Application;

return function (Application $app): void {
    $app->get('/health', fn() => ['ok' => true]);
};
```

Then call `(require __DIR__ . '/routes/http.php')($app);` before `listen()` or
`run()`. Services and lifecycle observers follow the same registration phase.
Use absolute paths based on `__DIR__` for views and downloadable files.

## What to do next

Read [routing](routing.md), [request validation](requests.md), and
[lifecycle ownership](lifecycle.md) before adding persistent connections or shared
services. Use the [example catalog](../examples/README.md) for runnable versions
of each topic.
