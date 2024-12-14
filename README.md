# Oktaax

![Version](https://img.shields.io/badge/version-v2.0.0-blue)  
![PHP](https://img.shields.io/badge/php-8.1%2B-blue)  
![OpenSwoole](https://img.shields.io/badge/OpenSwoole-Compatible-orange)  
![MIT License](https://img.shields.io/badge/license-MIT-green)  

**Oktaax** is an OpenSwoole HTTP & WebSocket wrapper library. It is designed for developers who want to build high-performance, asynchronous PHP applications.

---

## ⚡ Quick Start

### Requirements
- PHP 8.1+  
- OpenSwoole PHP extension  
*(Note: OpenSwoole does not support PHP 8.4 yet.)*

---

### Installation

To install **Oktaax**, use Composer:

```bash
composer require jefyokta/oktaax
```

---

## Hello World HTTP Example

Create a file named `index.php` with the following content:

```php
<?php

require 'vendor/autoload.php';

use Oktaax\Oktaax;
use Oktaax\Http\Request;
use Oktaax\Http\Response;

$app = new Oktaax;

$app->get("/", function (Request $request, Response $response) {
    $response->end("Hello World");
});

$app->listen(3000);
```

Run the server with:

```bash
php index.php
```

Open your browser and navigate to [http://localhost:3000](http://localhost:3000).

---

## 🌐 All-In-One Server

If you need both HTTP and WebSocket support in one class, you can use the `HasWebsocket` trait:

```php
<?php

require 'vendor/autoload.php';

use Oktaax\Oktaax;
use Oktaax\Websocket\HasWebsocket;
use Oktaax\Http\Request;
use Oktaax\Http\Response;
use Oktaax\Websocket\Client;
use Oktaax\Websocket\Server;

$app = new class extends Oktaax {
    use HasWebsocket;
};

$app->get("/user", function (Request $request, Response $response) {
    $response->end("User endpoint hit!");
});

$app->ws('welcome', function (Server $server, Client $client) {
    $server->reply($client, "Hi Client {$client->fd}");
});

$app->listen(3000);
```

Run the server as usual:

```bash
php index.php
```

---

## 📡 WebSocket 

Here is an example of creating a WebSocket **Channel**:

###  Channel

Create a channel class that implements the `Channel` interface.

```php
<?php

use Oktaax\Interfaces\Channel;
use Oktaax\Websocket\Client;

class EvenChannel implements Channel
{
    public function eligable(Client $client): bool
    {
        return $client->fd % 2 === 0;
    }
}
```



```php
<?php

$app->ws('even', function (Server $server, Client $client) {
    $server->toChannel(EvenChannel::class)->broadcast(function ($client) {
        return "Hello {$client->fd}! You have an even fd!";
    });
});
```

---

## HTTP

```php
<?php

$app = new Oktaax;

$app
    ->get("/", fn($request, $response) => $response->end("Welcome to Oktaax"))
    ->get("/user/{username}", function ($request, $response) {
        $user = $request->params['username'];
        $response->end("Hello, {$user}");
    })
    ->post("/user", function ($request, $response) {
        $data = $request->post;
        $response->end("User created with data: " . json_encode($data));
    })
    ->put("/user/{id}", function ($request, $response) {
        $id = $request->params['id'];
        $response->end("User {$id} updated");
    })
    ->delete("/user/{id}", function ($request, $response) {
        $id = $request->params['id'];
        $response->end("User {$id} deleted");
    });

$app->listen(3000);
```

---

## Trait and Server Options


### ```Oktaax\Oktaax``` 
is a class that include Requestable and WithBlade traits. You also can get it's instance with function oktaax()



