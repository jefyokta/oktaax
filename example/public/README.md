# Oktaax

![Version](https://img.shields.io/badge/version-v2.0.0-blue)

![PHP](https://img.shields.io/badge/php-8.1%2B-blue)
![OpenSwoole](https://img.shields.io/badge/OpenSwoole-Compatible-orange)
![MIT License](https://img.shields.io/badge/license-MIT-green)

Oktaax is a lightweight and powerful PHP library that wraps the OpenSwoole HTTP server. It's designed for developers who want to build high-performance, asynchronous PHP applications with syntaxes inspired by ExpressJs .

## Features

- Simple, intuitive API for creating routes
- Leverages OpenSwoole for asynchronous HTTP handling
- Easy to set up and extend

## Installation

```bash
composer require jefyokta/oktaax
```

## What news in v2.0.0?

### Oktaax\Oktaa now have no constructor & start() method

we removed consturctor and start() method.
now you would need to use Oktaa::listen(); to start and define port and host for server.

```php
<?php
$app = new Oktaa();

$app->get("/", function(Request $req, Response $res){

  return  $res->end("hello from okta !");

});

$app->listen(8000,"127.0.0.1",function($url){

    Console::log("Server Started on {$url}");
});


```

### Dynamic route

The dynamic route feature is one of the standout features. The examples you've provided are clear. Consider adding more detailed examples where the routes handle more complex data processing or response handling, which can help developers see the power and flexibility of the library

```php

$app->get("user/{id}",function($request,$response){

        //example: find a user in database
        $user = User::find($request->params['id'])
        $response->render("user",compact("user"));

    })
    ->delete("user/{id}",function($request,$response){

        $response->end("User {$request->params['id']} has been deleted");
    })
    ->put("user/{id}",function($request,$response){

        $response->end("User {$request->params['id']} has been updated");
    })
```

### Crsf

Now you can enable csrf with Oktaa::useCsfr().

this method require appkey 1 argument and 1 expire as optional argument.

##### example

```php
<?php

$app = new Oktaa();


//the second argument is optional, default would be 5 min
$app->useCsrf('secret',60);

```

##### Including csrf token in blade view

For Oktaax versions below v2.xx, including `@csrf` in a Blade view would cause an error due to the lack of a handler for this directive. However, starting from v2.xx, you can use `@csrf` just like in Laravel's Blade.

```html
<form method="post" action="/users">
  <!-- <input  type="hidden" name="_token" value="eyjgeneratetokenblablabla"> -->
  @csrf
</form>
```

## Example Usage

### Quick Start

```php

<?php


require __DIR__."/vendor/autoload.php";

use Oktaax\Http\Response;
use Oktaax\Http\Request;
use Oktaax\Http\ResponseJson;
use Oktaax\Oktaa;

$app = new Oktaa("127.0.0.1",80);

$app->get("/",function(Request $req,Response $res){
    $res->render('myview');

});

$app->get("/users",function(Request $req,Response $res){

    $users = fetchusers(); // example function
    $res->json(new ResponseJson($users,'here all of the users','no error'))
});

$app->start()

```

### Websocket

You can enable the WebSocket server using Oktaa::enableWebsocket() or by creating a new class that inherits from Oktaax.

```php
$app = new Oktaa;

$app->ws("/", function($server, $frame) {
    $server->push($frame->fd, "Hi, this is a message from the server.");
});

$app->enableWebsocket()->listen(8000, "127.0.0.1", function($url) {});

// Or with a new class.

class MyClass extends Oktaax {
    use HasWebsocket;

    public function __construct() {
        $this->app()->listen(8000, "127.0.0.1", function($url) {});
    }

    private function app() {
        $this->ws("/", function($server, $frame) {
            $server->push($frame->fd, "Hi, this is a message from the server.");
        });
    }
}

new MyClass();


```

### Using Oktaax

The Oktaa class is an inheritance of Oktaax and includes the WebSocket server. If you want to use a class without the WebSocket server, you can use Oktaax instead of Oktaa.

```php

$app = new Oktaax;

$app->ws("/",$aHandler); //it would cause an error

// If you want to enable WebSocket without the Oktaa class:
$app = new class extends Oktaax {
    use HasWebsocket;
};

$app->ws("/", function($server, $frame) {
    $server->push(....);
});
```

## Enabling SSL

```php

$app = new Oktaa;

$app->withSSL(__DIR__ . "/path-to-your.cert", __DIR__ . "/path-to-your.key")->listen(443, "127.0.0.1", function($url) {
    echo "Server started on $url";
});
```

## Configuration

### OpenSwoole Http Server Configuration

If you want to set configuration for Openswoole http server configuration , you can use Oktaax::setServer().
Check full list server configuration on [OpenSwoole Server Configuration](https://openswoole.com/docs/modules/swoole-server/configuration)

```php
<?php
$app->setServer([
    'reactor_num' => 2,
    'worker_num' => 4,
    'backlog' => 128,
    'max_request' => 50,
    'dispatch_mode' => 1,
]);

//or

$app->setServer('reactor_num',2);

```

### Oktaa Configuration

You can customize various settings like the directory for views, log, and etc.

```php
<?php
// viewsDir by default is views/
// logDir by default is log

$app->set("viewsDir","path/to/your/viewsDir");


// Now it will looking for path/to/your/viewsDir/index.php
$app->get('/',fn($req,$res)=>$res->render('index'));

```

all of configurations

```php
<?php

    private array $config = [
        "viewsDir" => "views/",
        "logDir" => "log",
        "render_engine" => null,
        "blade" => [
            "cacheDir" => null,
            "functionsDir" => null
        ],
        "useOktaMiddleware" => true,
        "sock_type" => null,
        "mode" => null,
        "withWebsocket" => false,
        "publicDir" => "public",
        "app" => [
            "key" => null,
            "name" => "oktaax",
            "useCsrf" => false,
            "csrfExp" => (60 * 5)
        ]
    ];

```

## Middleware Support

### Global Middleware

Global middleware runs for every request

```php
<?php

use Oktaax\Http\Response;
use Oktaax\Http\Request;
use Oktaax\Oktaa;

$app = new Oktaax();


$mymiddleware = function(Request $req, Response $res, $next){

    echo "This is a Global middleware";
    $next()
};
$app->use($mymiddleware);

```

### Route Middleware

You can also define route-specific middleware.

```php
$tokenVerify =function(Request $req, Response $res, $next){

    echo "This is a Route Middleware for route {$req->server['request_uri']}";
    //your token verify logic here

    //decoded token example
    $user = ["username"=>"jefyokta","id"=>1];
    $next((object) $user);
};

$app->get('/users',function(Request $req, Response $res,$user){

     $users = fetchusers(); // example function
    // another logic here
    $username = $user->username;
    $res->json(new ResponseJson($users,"hi {$username}!, this is all of the users,",'no error'));

},$tokenVerify);
```

With Class

```php


class someMiddleware{

    public function handle(Request $request , Response $response, callable $next){
        $request->passedmiddleware = true;
        $next();
    }
}

//with array
$app->get("/",function($request,$response){
    if($request->passedmiddleware){
        $response->end("passed");
    }

} ,[someMiddleware::class,'handle']);

// Or with string

// It's better to call the class with its namespace instead of just the class name to avoid confusion.

$app->get("/", function($request, $response) {
    if ($request->passedMiddleware) {
        $response->end("passed");
    }
}, 'someMiddleware.handle');

```

## Response Class

Oktaax has its own **Response** class that extends the **OpenSwoole\Http\Response** methods. It adds json and render methods. You can still access the original **OpenSwoole\Http\Response** via the $response property.

- For the json method, you must pass Oktaax\Http\ResponseJson as its argument to maintain consistent response data.

```php
// ResponseJson class params
public function __construct(?array $data = [], ?string $msg = null, $error = null){}

```

### Properties

```PHP
<?php

    /**
     * @var \OpenSwoole\Http\Response The Swoole HTTP response instance.
     */
    public $response;

    /**
     * @var array Configuration array for the response.
     */
    private array $config;

    /**
     * @var int HTTP status code for the response.
     */
    public $status = 200;

    public Request $request;


```

### Methods

\Oktaax\Http\Response has a general method as a response class, like render, cookies, redirect, etc.

```php

$response->render("view",["data"=>$data]);
.....
// send a message before go back
$response->with("hei, this is a temporary message")->back()

//or error message

$response->withError("hei, this is an error message due to your incomplete request")->back()
```

## Request Class

### Validation

if you wanna make sure that the request body has eligable to be processed, you can use validate message

example:

```php

$rules = [

    "name"=>"required|min:3|max:100",
    "email"=>"required|email",
    "password"=>"required|confirmed|min:8|max|200"

    ];

$validate =$request->validate($rules);

//you can check errors with 

if($request->errors !== null){
    //do somethings
};

//or you can get the data or errors with

 [$result,$errors]= $validate->getAll();

 if($errors !== null){
    //do something
 }


```
