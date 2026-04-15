# Oktaax

![Version](https://img.shields.io/badge/version-v2.0.0-blue)
![PHP](https://img.shields.io/badge/php-8.1%2B-blue)
![OpenSwoole](https://img.shields.io/badge/OpenSwoole-Compatible-orange)
![MIT License](https://img.shields.io/badge/license-MIT-green)

Oktaax is a lightweight, high-performance PHP HTTP and WebSocket server framework built on **Swoole**. It is designed for real-time applications and API-first workflows with minimal boilerplate, featuring built-in asynchronous programming support with Promises and coroutines.

## Table of Contents

- [🚀 Requirements](#-requirements)
- [📦 Installation](#-installation)
- [🔧 Quick Start](#-quick-start)
- [🌐 HTTP Routing](#-http-routing)
- [🧩 Request API](#-request-api)
- [🧩 Response API](#-response-api)
- [⚡ Asynchronous Programming](#-asynchronous-programming)
- [🔒 Authentication & Security](#-authentication--security)
- [🛠️ Server Configuration](#-server-configuration)
- [🕸️ Middleware](#-middleware)
- [💬 WebSocket Support](#-websocket-support)
- [📊 Error Handling](#-error-handling)
- [📁 File Uploads](#-file-uploads)
- [📊 Benchmarks](#-benchmarks)
- [📚 Examples](#-examples)
- [🤝 Contributing](#-contributing)
- [📄 License](#-license)

---

## 🚀 Requirements

- PHP 8.1+
- Swoole extension installed (`pecl install swoole` or `pecl install openswoole`)

---

## 📦 Installation

```bash
composer require jefyokta/oktaax
```

---

## 🔧 Quick Start

### Basic HTTP Server

```php
<?php
require 'vendor/autoload.php';

use Oktaax\Oktaax;
use Oktaax\Http\Request;
use Oktaax\Http\Response;

$app = new Oktaax();

$app->get('/', function (Request $request, Response $response) {
    $response->end('Hello World!');
});

$app->listen(3000);
```

Start the server and visit `http://localhost:3000`.

### HTTP + WebSocket Server

```php
<?php
require 'vendor/autoload.php';

use Oktaax\Oktaax;
use Oktaax\Trait\HasWebsocket;

$app = new class extends Oktaax {
    use HasWebsocket;
};

$app->get('/', fn($req, $res) => $res->end('HTTP + WS Server'));

$app->ws('hello', function ($server, $client) {
    $server->reply($client, ['message' => 'Hello from WebSocket!']);
});

$app->listen(3000);
```

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

### Basic Routes

```php
$app->get('/users', function ($req, $res) {
    $res->json(['users' => []]);
});

$app->post('/users', function ($req, $res) {
    $data = $req->body();
    // Create user logic
    $res->status(201)->json(['created' => true]);
});
```

### Dynamic Parameters

```php
$app->get('/users/{id}', function ($req, $res) {
    $id = $req->params['id'];
    $res->json(['user_id' => $id]);
});

$app->get('/posts/{category}/{slug}', function ($req, $res) {
    $category = $req->params['category'];
    $slug = $req->params['slug'];
    // Fetch post logic
    $res->json(['category' => $category, 'slug' => $slug]);
});
```

### Route Groups with Middleware

```php
$app->middleware([$authMiddleware], function ($router) {
    $router->get('/profile', $profileHandler);
    $router->post('/settings', $settingsHandler);
    $router->delete('/account', $deleteHandler);
});
```

### Path-specific Middleware

```php
$app->useFor('/api', function ($req, $res, $next) {
    $res->header('X-API-Version', '1.0');
    $next();
});
```

---

## 🧩 Request API

The `Request` object provides extensive helpers for accessing HTTP request data:

### Query Parameters & Body

```php
$app->get('/search', function ($req, $res) {
    $query = $req->input('q', 'default');
    $page = $req->input('page', 1);
    $limit = $req->input('limit', 10);

    // Search logic here
    $res->json(['query' => $query, 'page' => $page]);
});

$app->post('/submit', function ($req, $res) {
    $name = $req->post('name');
    $email = $req->post('email');
    $data = $req->body(); // Raw body as array

    $res->json(['received' => $data]);
});
```

### Headers & Cookies

```php
$app->get('/headers', function ($req, $res) {
    $userAgent = $req->header('User-Agent');
    $authToken = $req->header('Authorization');
    $sessionId = $req->cookie('session_id');

    $res->json([
        'user_agent' => $userAgent,
        'auth_token' => $authToken,
        'session_id' => $sessionId
    ]);
});
```

### Content Type Detection

```php
$app->post('/upload', function ($req, $res) {
    if ($req->isJson()) {
        $data = $req->json();
    } elseif ($req->isFormSubmission()) {
        $data = $req->body();
    }

    $res->json(['data' => $data]);
});
```

### Validation

```php
$app->post('/register', function ($req, $res) {
    $validated = $req->validate([
        'email' => 'required|email',
        'password' => 'required|min:8',
        'name' => 'required|string'
    ]);

    if ($validated->fails()) {
        return $res->status(422)->json(['errors' => $validated->errors()]);
    }

    // Registration logic
    $res->json(['message' => 'User registered successfully']);
});
```

---

## 🧩 Response API

The `Response` object offers fluent methods for crafting HTTP responses:

### JSON Responses

```php
$app->get('/api/users', function ($req, $res) {
    $users = [
        ['id' => 1, 'name' => 'John'],
        ['id' => 2, 'name' => 'Jane']
    ];

    return $res->json($users);
});
```

### Status Codes & Headers

```php
$app->post('/api/users', function ($req, $res) {
    // Create user logic
    $res->status(201)
        ->header('Location', '/api/users/123')
        ->json(['id' => 123, 'created' => true]);
});
```

### Redirects

```php
$app->get('/old-path', function ($req, $res) {
    $res->redirect('/new-path', 301);
});

$app->get('/dashboard', function ($req, $res) {
    if (!$req->cookie('auth')) {
        return $res->redirect('/login');
    }
    $res->end('Welcome to dashboard');
});
```

### Cookies

```php
$app->post('/login', function ($req, $res) {
    // Auth logic
    $res->cookie('session', 'abc123', time() + 3600, '/', null, false, true)
        ->json(['logged_in' => true]);
});

$app->post('/logout', function ($req, $res) {
    $res->cookie('session', '', time() - 3600)
        ->json(['logged_out' => true]);
});
```

### File Downloads

```php
$app->get('/download/{filename}', function ($req, $res) {
    $filename = $req->params['filename'];
    $filepath = "/path/to/files/$filename";

    if (file_exists($filepath)) {
        $res->header('Content-Type', mime_content_type($filepath))
            ->sendfile($filepath);
    } else {
        $res->status(404)->end('File not found');
    }
});
```

### Views (with PHP templates)

```php
$app->setView(new \Oktaax\Views\PhpView(__DIR__ . '/views'));

$app->get('/page', function ($req, $res) {
    $res->render('welcome', ['title' => 'Welcome Page']);
});
```

---

## ⚡ Asynchronous Programming

Oktaax provides powerful asynchronous programming capabilities using Swoole coroutines and Promises.

### Promises

```php
use Oktaax\Core\Promise;

$app->get('/async-data', function ($req, $res) {
    $promise = new Promise(function ($resolve, $reject) {
        // Simulate async operation
        setTimeout(function () use ($resolve) {
            $resolve(['data' => 'Async result']);
        }, 1000);
    });

    $promise->then(function ($data) use ($res) {
        $res->json($data);
    })->catch(function ($error) use ($res) {
        $res->status(500)->json(['error' => $error]);
    });

    return $promise; // Return promise for auto-resolution
});
```

### Async/Await Pattern

```php
use function Oktaax\async;
use function Oktaax\await;

$app->get('/async-await', async(function ($req, $res) {
    $data1 = await(fetchDataFromAPI('/api/endpoint1'));
    $data2 = await(fetchDataFromAPI('/api/endpoint2'));

    $combined = array_merge($data1, $data2);
    $res->json($combined);
}));
```

### Promise.all for Parallel Operations

```php
$app->get('/parallel', async(function ($req, $res) {
    $promises = [
        fetchUserData(1),
        fetchUserData(2),
        fetchUserData(3)
    ];

    $results = await(Promise::all($promises));
    $res->json(['users' => $results]);
}));
```

### Async Middleware

```php
$app->use(async(function ($req, $res, $next) {
    $user = await(authenticateUser($req->header('Authorization')));
    $req->user = $user;
    $next();
}));
```

### Async File Operations

```php
$app->get('/read-file', async(function ($req, $res) {
    $filename = $req->input('file', 'default.txt');

    try {
        $content = await(readFileAsync($filename));
        $res->json(['content' => $content]);
    } catch (Exception $e) {
        $res->status(404)->json(['error' => 'File not found']);
    }
}));
```

### Database Operations (Async)

```php
$app->get('/users/{id}', async(function ($req, $res) {
    $id = $req->params['id'];

    try {
        $user = await(queryDatabase("SELECT * FROM users WHERE id = ?", [$id]));
        $res->json($user);
    } catch (Exception $e) {
        $res->status(500)->json(['error' => 'Database error']);
    }
}));
```

---

## 🔒 Authentication & Security

### CSRF Protection

```php
$app->useCsrf('your-secret-key', 300); // 5 minutes expiry

$app->get('/form', function ($req, $res) {
    $token = xcsrf_token();
    $res->json(['csrf_token' => $token]);
});

$app->post('/submit', function ($req, $res) {
    // CSRF token is automatically validated
    $res->json(['submitted' => true]);
});
```

### JWT Authentication

```php
use Firebase\JWT\JWT;

$app->post('/login', function ($req, $res) {
    $credentials = $req->body();

    // Validate credentials
    if ($credentials['username'] === 'admin' && $credentials['password'] === 'password') {
        $payload = [
            'iss' => 'oktaax-app',
            'sub' => 1,
            'iat' => time(),
            'exp' => time() + 3600
        ];

        $jwt = JWT::encode($payload, 'your-secret-key', 'HS256');
        $res->json(['token' => $jwt]);
    } else {
        $res->status(401)->json(['error' => 'Invalid credentials']);
    }
});

$app->middleware(function ($req, $res, $next) {
    $token = $req->header('Authorization');

    if (!$token || !str_starts_with($token, 'Bearer ')) {
        return $res->status(401)->json(['error' => 'No token provided']);
    }

    try {
        $decoded = JWT::decode(substr($token, 7), 'your-secret-key', ['HS256']);
        $req->user = $decoded;
        $next();
    } catch (Exception $e) {
        $res->status(401)->json(['error' => 'Invalid token']);
    }
}, function ($router) {
    $router->get('/protected', function ($req, $res) {
        $res->json(['message' => 'Protected resource', 'user' => $req->user]);
    });
});
```

### HTTPS Support

```php
$app->withSSL('/path/to/cert.pem', '/path/to/key.pem');
$app->listen(443);
```

---

## 🛠️ Server Configuration

### Basic Configuration

```php
$app->setServer([
    'worker_num' => 4,
    'max_request' => 1000,
    'daemonize' => false,
    'log_file' => '/var/log/oktaax.log'
]);

$app->listen(3000);
```

### Advanced Options

```php
$app->setServer('worker_num', 8)
    ->setServer('task_worker_num', 2)
    ->setServer('max_conn', 10000)
    ->setServer('buffer_output_size', 32 * 1024 * 1024); // 32MB
```

### Application Configuration

```php
use Oktaax\Types\OktaaxConfig;

$app->setConfig(new OktaaxConfig([
    'debug' => true,
    'timezone' => 'UTC',
    'upload' => [
        'max_size' => 10 * 1024 * 1024, // 10MB
        'temp_dir' => '/tmp/uploads'
    ]
]));
```

---

## 🕸️ Middleware

### Global Middleware

```php
$app->use(function ($req, $res, $next) {
    // CORS headers
    $res->header('Access-Control-Allow-Origin', '*');
    $res->header('Access-Control-Allow-Methods', 'GET,POST,PUT,DELETE,OPTIONS');
    $res->header('Access-Control-Allow-Headers', 'Content-Type,Authorization');

    // Logging
    $start = microtime(true);
    $next();
    $duration = microtime(true) - $start;
    Console::log("Request to {$req->path()} took {$duration}s");
});
```

### Class-based Middleware

```php
use Oktaax\Contracts\Middleware;

class AuthMiddleware implements Middleware {
    public function handle($req, $res, $next) {
        $token = $req->header('Authorization');

        if (!$token) {
            return $res->status(401)->json(['error' => 'Unauthorized']);
        }

        // Validate token logic
        $req->user = ['id' => 1, 'name' => 'John'];
        $next();
    }
}

class RateLimitMiddleware implements Middleware {
    private $requests = [];

    public function handle($req, $res, $next) {
        $ip = $req->header('X-Forwarded-For') ?? '127.0.0.1';
        $now = time();

        // Simple rate limiting (max 10 requests per minute)
        if (!isset($this->requests[$ip])) {
            $this->requests[$ip] = [];
        }

        $this->requests[$ip] = array_filter($this->requests[$ip], function ($time) use ($now) {
            return $now - $time < 60;
        });

        if (count($this->requests[$ip]) >= 10) {
            return $res->status(429)->json(['error' => 'Too many requests']);
        }

        $this->requests[$ip][] = $now;
        $next();
    }
}

$app->use(new AuthMiddleware());
$app->use(new RateLimitMiddleware());
```

### Async Middleware

```php
$app->use(async(function ($req, $res, $next) {
    $user = await(authenticateUser($req->header('Authorization')));
    $req->user = $user;
    $next();
}));
```

---

## 💬 WebSocket Support

Extend Oktaax with WebSocket capabilities using the `HasWebsocket` trait:

```php
use Oktaax\Trait\HasWebsocket;

$app = new class extends Oktaax {
    use HasWebsocket;
};
```

### Basic WebSocket Events

```php
$app->ws('message', function ($server, $client) {
    $data = $client->data; // Received data
    $server->reply($client, ['echo' => $data]);
});

$app->ws('broadcast', function ($server, $client) {
    $message = $client->data['message'];
    $server->broadcast(['message' => $message, 'from' => $client->fd]);
});
```

### Connection Management

```php
$app->gate(function ($server, $request) {
    // Validate connection before accepting
    $token = $request->header('Sec-WebSocket-Protocol');

    if (!$token || !validateToken($token)) {
        return false; // Reject connection
    }

    return true; // Accept connection
});

$app->exit(function ($server, $fd) {
    Console::log("Client $fd disconnected");
    // Cleanup logic
});
```

### Client Data Storage

```php
$app->table(function (\Swoole\Table $table) {
    $table->column('user_id', \Swoole\Table::TYPE_INT);
    $table->column('username', \Swoole\Table::TYPE_STRING, 32);
    $table->create();
}, 1024);

$app->ws('join', function ($server, $client) {
    $userId = $client->data['user_id'];
    $username = $client->data['username'];

    $server->table->set($client->fd, [
        'user_id' => $userId,
        'username' => $username
    ]);

    $server->broadcast(['type' => 'user_joined', 'username' => $username]);
});
```

### Event Handling

```php
$app->withOutEvent(function ($server, $client) {
    $server->reply($client, ['error' => 'Unknown event']);
});
```

---

## 📊 Error Handling

### Global Exception Handlers

```php
use Oktaax\Core\Application;

$app->listen(3000, '127.0.0.1', function ($url, Application $coreApp) {
    $coreApp->catch(\Oktaax\Exception\ValidationException::class, function ($e) {
        response()->status(422)->json(['errors' => $e->getErrors()]);
    });

    $coreApp->catch(\Oktaax\Exception\HttpException::class, function ($e) {
        response()->status($e->getStatusCode())->json(['error' => $e->getMessage()]);
    });

    $coreApp->catch(Exception::class, function ($e) {
        Console::error($e->getMessage());
        response()->status(500)->json(['error' => 'Internal server error']);
    });
});
```

### Custom Error Responses

```php
$app->get('/test-error', function ($req, $res) {
    throw new \Oktaax\Exception\HttpException('Custom error message', 400);
});
```

### Async Error Handling

```php
$app->get('/async-error', async(function ($req, $res) {
    try {
        $result = await(mayFailAsync());
        $res->json(['result' => $result]);
    } catch (Exception $e) {
        $res->status(500)->json(['error' => $e->getMessage()]);
    }
}));
```

---

## 📁 File Uploads

### Basic File Upload

```php
$app->post('/upload', function ($req, $res) {
    $files = $req->files();

    if (empty($files)) {
        return $res->status(400)->json(['error' => 'No files uploaded']);
    }

    $uploaded = [];
    foreach ($files as $file) {
        $filename = uniqid() . '_' . $file['name'];
        $destination = "/uploads/$filename";

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            $uploaded[] = $filename;
        }
    }

    $res->json(['uploaded' => $uploaded]);
});
```

### Chunked Uploads

```php
$app->post('/upload-chunk', function ($req, $res) {
    $chunk = $req->file('chunk');
    $chunkNumber = $req->input('chunkNumber');
    $totalChunks = $req->input('totalChunks');
    $filename = $req->input('filename');

    $tempDir = '/tmp/uploads/';
    $chunkFile = $tempDir . $filename . '.part' . $chunkNumber;

    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }

    if (move_uploaded_file($chunk['tmp_name'], $chunkFile)) {
        // Check if all chunks are uploaded
        $allChunks = true;
        for ($i = 0; $i < $totalChunks; $i++) {
            if (!file_exists($tempDir . $filename . '.part' . $i)) {
                $allChunks = false;
                break;
            }
        }

        if ($allChunks) {
            // Combine chunks
            $finalFile = '/uploads/' . $filename;
            $handle = fopen($finalFile, 'wb');

            for ($i = 0; $i < $totalChunks; $i++) {
                $chunkFile = $tempDir . $filename . '.part' . $i;
                fwrite($handle, file_get_contents($chunkFile));
                unlink($chunkFile);
            }

            fclose($handle);
            $res->json(['status' => 'complete', 'file' => $filename]);
        } else {
            $res->json(['status' => 'chunk_received', 'chunk' => $chunkNumber]);
        }
    } else {
        $res->status(500)->json(['error' => 'Failed to save chunk']);
    }
});
```

### Async File Processing

```php
$app->post('/upload-async', async(function ($req, $res) {
    $file = $req->file('file');

    if (!$file) {
        return $res->status(400)->json(['error' => 'No file uploaded']);
    }

    $filename = uniqid() . '_' . $file['name'];
    $destination = "/uploads/$filename";

    // Process file asynchronously (e.g., resize image, scan for viruses)
    $processed = await(processFileAsync($file['tmp_name'], $destination));

    $res->json(['uploaded' => $filename, 'processed' => $processed]);
}));
```

---

## 📊 Benchmarks

Compare Oktaax performance with other frameworks:

```bash
cd benchmark
chmod +x run.sh
./run.sh
```

The benchmark includes:
- Oktaax server
- Express.js server
- Results comparison

---

## 📚 Examples

The `examples/` directory contains runnable examples demonstrating various features:

- `http-basic.php` - Basic HTTP server
- `websocket.php` - WebSocket server
- `middleware.php` - Global middleware
- `promise.php` - Promise usage
- `async-response.php` - Async responses
- `route-group.php` - Route grouping
- `send-response.php` - Response methods
- `console.php` - Logging
- `invoker.php` - Dependency injection
- `log.php` - Finally hooks
- `error-handling.php` - Exception handling
- `validation.php` - Request validation
- `file-upload.php` - File uploads
- `jwt-auth.php` - JWT authentication
- `streaming.php` - Streaming responses
- `custom-middleware.php` - Class-based middleware
- `configuration.php` - Server configuration
- `async-database.php` - Async database operations
- `async-file-operations.php` - Async file I/O

Run any example:

```bash
php examples/http-basic.php
```

---

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

---

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

*Built with ❤️ using Swoole for high-performance PHP applications.*