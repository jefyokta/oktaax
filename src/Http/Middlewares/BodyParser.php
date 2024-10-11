<?php

namespace Oktaax\Http\Middleware;

use Oktaax\Http\Request;
use Oktaax\Http\Response;

class BodyParser
{

    public static function index()
    {
        return function (Request $req, Response $res, callable $next) {
            $rawBody = $req->request->rawContent();
            $data = json_decode($rawBody, true);
            if ($data) {
                $req->body = $data;
            }
            $next();
        };
    }
}
