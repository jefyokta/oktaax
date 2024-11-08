<?php

use Oktaax\Console;
use Oktaax\Http\Request;
use Oktaax\Http\Response;
use Oktaax\Oktaax;

require_once "vendor/autoload.php";


$app = new Oktaax;


$app
    ->get('/company/{companyId}/user/{userid}', function (Request $request, Response $response, string $name) {
        $response->header('content-type', 'application/json');
        $response->end(json_encode(["params" => $request->params, "name" => $name], JSON_PRETTY_PRINT));
    }, function ($req, $res, $next) {

        $next('jepi');
    })
    ->get('/company/{companyId}', function (Request $request, Response $response, string $name) {
        $response->header('content-type', 'application/json');
        $response->end(json_encode(["params" => $request->params, "name" => $name], JSON_PRETTY_PRINT));
    }, function ($req, $res, $next) {

        $next('oktaax');
    })
    ->get('/company/facebook', function (Request $request, Response $response) {
        $response->header('content-type', 'application/json');
        $response->end(json_encode(["params" => $request->params], JSON_PRETTY_PRINT));
    })
    ->withSSL(__DIR__ . "/../cert/jepi.okta.crt", __DIR__ . "/../cert/jepi.okta.key")
    ->listen(9501, 'jepi.okta', function ($url) {
        Console::info("server started on $url");
    });
