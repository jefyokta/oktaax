# Oktaax

![Version](https://img.shields.io/badge/version-v2.0.0-blue)
![PHP](https://img.shields.io/badge/php-8.1%2B-blue)
![OpenSwoole](https://img.shields.io/badge/OpenSwoole-Compatible-orange)
![MIT License](https://img.shields.io/badge/license-MIT-green)

Oktaax is a lightweight, high-performance PHP HTTP and WebSocket server framework built on **Swoole**. It is designed for real-time applications and API-first workflows with minimal boilerplate.

---

## 🚀 Requirements

- PHP 8.1+
- Swoole extension installed (`pecl install swoole`)

---

## 📦 Installation

```bash
composer require jefyokta/oktaax
```

---

## 🔧 Quick Start (HTTP)

```php
<?php
require 'vendor/autoload.php';

use Oktaax\Oktaax;
use Oktaax\Http\Request;
use Oktaax\Http\Response;

$app = new Oktaax();

$app->get('/', function (Request $request, Response $response) {
    $response->end('Hello World');
});

$app->listen(3000);
```

Open `http://localhost:3000`.

---

## 🌐 HTTP Routing

Oktaax supports all common HTTP verbs:

- `get(path, handler)`
- `post(path, handler)`
- `put(path, handler)`
- `delete(path, handler)`
- `patch(path, handler)`
- `options(path, handler)`
- `head(path, handler)`

Handlers can be:
- callable closures
- class method array `[ClassName::class, 'method']`
- string view route or controller placeholders (for future extension)

### Route with dynamic params

```php
$app->get('/user/{id}', function ($request, $response) {
    $id = $request->params['id'];
    $response->end("User: $id");
});
```

### Global middleware

```php
$app->use(function ($request, $response, $next) {
    // ...pre processing
    $next();
});
```

### Route-specific middleware and group middleware

```php
$app->get('/admin', $handler, $authMiddleware);

$app->middleware([$authMiddleware], function ($router) {
    $router->get('/profile', $profileHandler);
});
```

---

## 🧩 Request API

`Oktaax\Http\Request` exposes these useful helpers:

- `input(key, default)`
- `post(key)`
- `get(key)`
- `all()`
- `has(key)`
- `header(key)`
- `cookie(name)`
- `queryHas(key)`
- `userAgent()`
- `isJson()` / `isFormSubmission()`
- `wantsJson()` / `wantsJS()`
- `isMethod('GET')`
- `path()`
- `protocol()`, `host()`
- `validate(rules, data)`
- `body(key)`, `json(key)`
- `bodies()`, `parameters()`

---

## 🧩 Response API

`Oktaax\Http\Response` offers full fluent helpers:

- `header(key, value)`
- `status(code)`
- `sendStatus(code)`
- `end(content)`
- `json(new Oktaax\Http\ResponseJson([...]))` (or response->json())
- `render('view', data)`
- `sendfile(path)`
- `redirect(path, status)`
- `back(default)`
- `cookie(name, value, expires, path, domain, secure, httponly, samesite, priority)`
- `with(msg)` / `withError(msg)` (flash cookies)
- `write(data)`
- `stream(callback, status, headers)`

---

## 🔒 CSRF protection

Enable CSRF token support on App request layer:

```php
$app->useCsrf('app_key_here', 300);
```

This sets internal `app.key`, `app.csrfExp`, and `app.useCsrf`.

---

## 🔐 HTTPS support

```php
$app->withSSL('/path/to/cert.pem', '/path/to/key.pem');
// alias: $app->securely('/path/to/cert.pem', '/path/to/key.pem');

$app->listen(443);
```

---

## 🛠️ Server options

```php
$app->setServer([
    'worker_num' => 4,
    'daemonize' => false,
]);

// or
$app->setServer('worker_num', 2);
```

---

## 🕸️ Route-specific middleware (path bind)

```php
$app->useFor('/api', $apiMiddleware);
```

---

## 🔁 Reload

```php
$app->reload();
```

---

## 🖼️ View engine

By default, Oktaax ships with PHP view support (`Oktaax\Views\PhpView` in `views/`).

```php
$app->setView(new Oktaax\Views\PhpView('views/'));
```

---

## 💬 Built-in global helpers

- `oktaax()` → `new Oktaax()`
- `xsocket()` → `new class extends Oktaax implements Xsocket { use Oktaax\Trait\HasWebsocket; }`
- `xrequest()` → current request instance
- `xserver()` → current Swoole server
- `xcsrf_token()` → request CSRF token

---

## 🧠 Application plumbing and advanced control

### Global exception and response resolvers

Oktaax's internal app object supports:

- `app->catch(ExceptionClass::class, handler)` to register custom exception handling
- `app->resolve(ResponseClass::class, handler)` or `app->respond(...)` to customize response types
- `app->inject(Request::class, 'method', InvokableClass::class)` to attach methods to Request/Response classes

### Application config access

- `app->setApplication(new Oktaax\Types\AppConfig(...))`
- `app->setConfig(new Oktaax\Types\OktaaxConfig(...))`

### Swoole-level event hooks

- `$app->on('start', $handler);` (any Swoole event not handled by framework)
- Built-in handled events are `request` and `workerstart`, other events are delegated directly.

---

## 🚀 Application core (recommended, stable)

`Oktaax\Core\Application` is the central request/response container and dispatcher.

- `app->catch(ExceptionClass::class, handler)` registers exception handlers.
- `app->resolve(ReturnType::class, handler)` / `app->respond(...)` registers custom response handlers.
- `app->inject(Request::class|Response::class, 'name', InvokableClass::class)` adds methods to request/response objects.
- `app->setApplication(AppConfig)` sets application options, including CSRF behavior.
- `app->setConfig(OktaaxConfig)` overrides framework config for view & storage.

The framework bootstraps this in `WorkerStart` event and automatically rewrites responses via `Router::handle` and `ReturnDispatcher`.

---

## ✅ Complete library usage example (HTTP + WebSocket + app lifecycle)

```php
<?php
require 'vendor/autoload.php';

use Oktaax\Oktaax;
use Oktaax\Trait\HasWebsocket;
use Oktaax\Http\Request;
use Oktaax\Http\Response;
use Oktaax\Core\Application;

// Create a combined HTTP + WS server
$app = new class extends Oktaax {
    use HasWebsocket;
};

// Set custom view by path
$app->setView(new \Oktaax\Views\PhpView(__DIR__ . '/views'));

// Global middleware (all routes)
$app->use(function ($req, $res, $next) {
    // Example CORS headers as middleware
    $res->header('Access-Control-Allow-Origin', '*');
    $res->header('Access-Control-Allow-Methods', 'GET,POST,PUT,DELETE,OPTIONS');
    $next();
});

// Route-specific middleware group
$app->middleware([
    function ($req, $res, $next) {
        if (!$req->header('authorization')) {
            return $res->status(401)->end('Unauthorized');
        }
        $next();
    }
], function ($router) {
    $router->get('/api/user', function ($req, $res) {
        return ['user' => 'oktaax'];
    });
});

// Basic HTTP routes
$app->get('/', function (Request $request, Response $response) {
    return $response->render('home', ['title' => 'Oktaax Home']);
});

$app->post('/form', function (Request $request, Response $response) {
    $name = $request->input('name', 'anonymous');
    return $response->json(new \Oktaax\Http\ResponseJson(['hello' => $name]));
});

$app->get('/user/{id}', function (Request $request, Response $response) {
    $id = $request->params['id'];
    return "User id: $id";
});

$app->useFor('/api', function ($req, $res, $next) {
    // path-specific middleware
    $req->header('x-api', 'oktaax');
    $next();
});

$app->useCsrf('myAppKey', 600);
$app->setServer(['worker_num' => 2]);

// WebSocket event setup
$app->ws('ping', function ($server, $client) {
    $server->reply($client, json_encode(['event' => 'pong']));
});

$app->gate(function ($server, $request) {
    // Optional gate check - if false, connection can be closed.
});

$app->exit(function ($server, $fd) {
    // Cleanup on client disconnect
});

$app->withOutEvent(function ($server, $client) {
    $server->reply($client, json_encode(['error' => 'event required']));
});

$app->broadcast([ 'event' => 'ready' ]);

// Application-level catch + respond (register in start callback)
$app->listen(3000, '127.0.0.1', function ($url, Application $coreApp) {
    
    $coreApp->catch(\Oktaax\Exception\HttpException::class, function ($e) {
        response()->status($e->getStatusCode())->end($e->getMessage());
    });
    $coreApp->respond(\Oktaax\Http\Support\StreamedResponse::class, function ($stream, $req, $res) {
        $stream->getCallback()(fn($chunk) => $res->write($chunk));
        $res->end();
    });

    echo "Server started at $url\n";
});
```

---

## 🔧 Request additions

`Oktaax\Http\Request` supports many helpers beyond standard routing:

- `hasHeader(key)`, `isAjax()`, `xhr()`, `isInertia()`
- `__invoke(key)` alias (not in original docs)
- `__toString()` returns JSON of request

---

## 🔧 Response additions

`Oktaax\Http\Response` also provides:

- `getSwooleResponse()` for native `Swoole\Http\Response`
- `stream($callback, $status, $headers)` for streaming responses
- `renderHttpError(code)` for built-in error page rendering

---

## 🌐 WebSocket Support

Use websocket-enabled server by extending and using `HasWebsocket`:

```php
<?php
require 'vendor/autoload.php';

use Oktaax\Oktaax;
use Oktaax\Trait\HasWebsocket;
use Oktaax\Websocket\Server;
use Oktaax\Websocket\Client;

$app = new class extends Oktaax {
    use HasWebsocket;
};

$app->get('/', fn($req, $res) => $res->end('OK'));

$app->ws('welcome', function (Server $server, Client $client) {
    $server->reply($client, "Hi Client {$client->fd}");
});

$app->gate(function (Server $server, $request) {
    // Connection gate (onOpen) logic
});

$app->exit(function (Server $server, $fd) {
    // connection close handling
});

$app->withOutEvent(function ($server, $client) {
    $server->reply($client, 'Event payload missing');
});

$app->table(function (\Swoole\Table $table) {
    // Initialize table values
}, 2048);

$app->listen(3000);
```

### WebSocket events

- `ws(eventName, handler)` to register an event handler
- `gate(handler)` on open
- `exit(handler)` on close
- `broadcast(payload)` send to all clients
- `withOutEvent(handler)` when incoming frame has no `event`

---

## 💡 Channels (optional pattern)

Define a channel condition via `Oktaax\Interfaces\Channel` and use any channel-friendly helper patterns.

---

## 🧪 Error handling

- 404 and errors are rendered via built-in `Response::renderHttpError(code)`.
- `Oktaax\Exception\HttpException` is thrown for no route match.

---

## 📊 Benchmarks

`benchmark/` contains scripts to compare Oktaax with Express:

- `benchmark/server.php`
- `benchmark/express_server.js`
- `benchmark/run.sh`

```bash
cd benchmark
chmod +x run.sh
./run.sh
```

---

## 🧾 Notes

- Oktaax is request-stateful and uses `Application::getInstance()` to share request/response inside middleware.
- This guide reflects latest API as of codebase inspection (aliases, route handling, websocket support, CSRF, and server config).
