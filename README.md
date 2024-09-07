# Oktaax

![PHP](https://img.shields.io/badge/php-7.4%2B-blue)
![OpenSwoole](https://img.shields.io/badge/OpenSwoole-Compatible-orange)
![MIT License](https://img.shields.io/badge/license-MIT-green)

Oktaax is a lightweight and powerful PHP library that wraps the OpenSwoole HTTP server. It's designed for developers who want to build high-performance, asynchronous PHP applications with syntaxes inspired by ExpressJs.

## Features

- Simple, intuitive API for creating routes
- Leverages OpenSwoole for asynchronous HTTP handling
- Easy to set up and extend

## Installation

```bash
composer require jefyokta/oktaax
```

## Example Usage

```php

<?php


require __DIR__."/vendor/autoload.php";

use Oktaax\Http\Response;
use Swoole\Http\Request;
use Oktaax\Http\APIResponse;
use Oktaax\Oktaa;

$app = new Oktaax("127.0.0.1",80);

$app->get("/",function(Request $req,Response $res){
    $res->render('myview');

});

$app->get("/users",function(Request $req,Response $res){

    $users = fetchusers(); // example function
    $res->json(new APIresponse($users,'here all of the users','no error'))
});

$app->start()

```

## Configuration

### OpenSwoole Http Server Configuration
If you want to set configuration for Openswoole http server configuration , you can use Oktaax::setServer().
Check full list Server Configuration On [OpenSwoole Server Configuration](https://openswoole.com/docs/modules/swoole-server/configuration)


```php
<?php
$app->setServer([
    'reactor_num' => 2,
    'worker_num' => 4,
    'backlog' => 128,
    'max_request' => 50,
    'dispatch_mode' => 1,
]);

 ```

### Oktaax Configuration
You can customize various settings like the directory for views, log, and etc.

```php
<?php
// viewsDir by default is views/
// logDir by default is log

$app->set("viewsDir","path/to/your/viewsDir");


// Now it will looking for path/to/your/viewsDir/index.php
$app->get('/',fn($req,$res)=>$res->render('index'));

```

## Middleware Support

### Global Middleware
Global middleware runs for every request
```php
<?php

use Oktaax\Http\Response;
use Swoole\Http\Request;
use Oktaax\Http\APIResponse;
use Oktaax\Oktaa;

$app = new Oktaax("127.0.0.1",80);


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
    $res->json(new APIresponse($users,"hi {$username}!, this is all of the users,",'no error'));

},[$tokenVerify]);

$app->start()

```


## Request and Response Classs

### Request Class
Oktaax still use **Request** Class from **OpenSwoole\Http\Request** Class.

### Response Class

Oktaax has its own **Response** class that extends the **OpenSwoole\Http\Response** methods. It adds json and render methods. You can still access the original **OpenSwoole\Http\Response** via the $response property.

- For the json method, you must pass Oktaax\Http\APIResponse as its argument to maintain consistent response data.
```php
// APIresponse class params
public function __construct(?array $data = [], ?string $msg = null, $error = null){}

```
