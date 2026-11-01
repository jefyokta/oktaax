# Oktaax

Build HTTP APIs, streamed responses, SSE, background tasks, and WebSocket services
with PHP and Swoole. Each application owns its configuration, router, services,
and lifecycle scopes.

**Start here:** [User guide](docs/README.md) · [Runnable examples](examples/README.md) ·
[API reference](docs/api-reference.md) · [Migration](docs/migration.md)

## Requirements

- PHP 8.3+ with the `swoole` extension loaded in the CLI.
- Composer. The repository's development dependencies require PHP 8.4+.
- A terminal: Oktaax runs its own server, rather than PHP-FPM or `php -S`.

```sh
composer require jefyokta/oktaax
php --ri swoole
```

The documentation describes the current source tree. For an existing project,
check the [migration guide](docs/migration.md): compatibility namespaces have been
removed.

## Your first server

Create `server.php` beside your application's `vendor/` directory:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Oktaax\Http\Request;
use Oktaax\Http\Response;
use Oktaax\Oktaax;

$app = new Oktaax();
$app->get('/health', fn() => ['ok' => true]);
$app->get(
    '/users/{id:\d+}',
    fn(Request $request) => [
        'id' => $request->param('id'),
    ],
);
$app->post('/users', function (Request $request, Response $response) {
    $user = $request->validate(['name' => 'required|string']);
    $response->status(201)->json($user);
});
$app->listen(3000, '127.0.0.1');
```

```sh
php server.php
```

In another terminal:

```sh
curl http://127.0.0.1:3000/health
curl -i -X POST http://127.0.0.1:3000/users \
  -H 'Content-Type: application/json' -d '{"name":"Ada"}'
```

The first request returns `{"ok":true}`. The second returns HTTP 201 and
`{"name":"Ada"}`. A missing name returns HTTP 422. Stop the server with Ctrl+C.

## Learn by topic

| Topic                                                  | Guide                                                 | Example recipe         |
| ------------------------------------------------------ | ----------------------------------------------------- | ---------------------- |
| Installation and application layout                    | [Getting started](docs/getting-started.md)            | `http`                 |
| Routes, middleware, authentication boundaries, CSRF    | [Routing](docs/routing.md)                            | `http`, `csrf`         |
| Input sources, JSON, uploads, validation               | [Requests](docs/requests.md)                          | `http`                 |
| JSON, templates, files, custom results, Inertia        | [Responses](docs/responses.md)                        | `responses`, `inertia` |
| Application, worker, request, and connection scopes    | [Lifecycle and services](docs/lifecycle.md)           | `lifecycle`            |
| Database models and transactions                       | [Database and Eloquent](docs/database.md)             | `database`, `mysql`    |
| Streaming and connection cleanup                       | [Streaming and SSE](docs/streaming.md)                | `sse`                  |
| Task workers, events, IPC, timers                      | [Tasks and events](docs/tasks-events.md)              | `tasks`, `events`      |
| JSON packets and browser client                        | [WebSocket](docs/websocket.md)                        | `websocket`            |
| Coroutine HTTP requests and body handling              | [HTTP client](docs/http-client.md)                    | `http-client`          |
| Error pages, debug mode, custom templates and handlers | [Error handling](docs/errors.md)                      | `errors`               |
| Configuration, runtimes, deployment                    | [Configuration and operations](docs/configuration.md) | all recipes            |
| Testing, checks, troubleshooting                       | [Testing](docs/testing.md)                            | all recipes            |

From a checkout of this repository:

```sh
composer install
php examples/guide/serve.php http
# Try the redesigned error pages:
OKTAAX_DEBUG=1 php examples/guide/serve.php errors
```

Run one server at a time, or set `OKTAAX_PORT` to use another port.
See [examples/README.md](examples/README.md) for commands and expected output.

## Development

Formatting uses Node.js/npm with Prettier and its PHP plugin:

```sh
npm ci
npm run format
npm run format:check
```

```sh
composer test
composer analyse -- --debug
composer test:integration
```

Integration tests start local servers and require loopback sockets and child
processes. They exercise the actual example factories as well as the runtime
kernel. See [testing](docs/testing.md) and the [architecture guide](docs/architecture.md).
