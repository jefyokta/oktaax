# Oktaax

![PHP](https://img.shields.io/badge/php-7.4%2B-blue)
![OpenSwoole](https://img.shields.io/badge/OpenSwoole-Compatible-orange)
![MIT License](https://img.shields.io/badge/license-MIT-green)

Oktaax is a simple PHP Library wrapper for the OpenSwoole HTTP server. It allows you to create high-performance HTTP applications with ease, combining the power of PHP with the performance of OpenSwoole. And also look familiar for javascript developers

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

### Set Method

```php
<?php
// viewsDir by default is views/
// log by default is log

$app->set("viewsDir","path/to/your/viewsDir")

$app->get('/',fn($req,$res)=>$res->render('index')) // Now it will looking for path/to/your/viewsDir/index.php

 ```


### Add middleware

```php

<?php

use Oktaax\Http\Response;
use Swoole\Http\Request;
use Oktaax\Http\APIResponse;
use Oktaax\Oktaa;

$app = new Oktaax("127.0.0.1",80);

// Global Middleware

$mymiddleware = function(Request $req, Response $res, $next){

    echo "This is a Global middleware";
    $next()
};

$app->use($mymiddleware);

// Route Middleware

$routemiddleware =function(Request $req, Response $res, $next){

    echo "This is a Route Middleware for route {$req->server['request_uri']}";
    $next()
};

$app->get('/endpoint',function(Request $req, Response $res){

},[$routemiddleware]);

$app->start()

```
