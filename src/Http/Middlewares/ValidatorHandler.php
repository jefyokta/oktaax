<?php

namespace Oktaax\Http\Middleware;

use Oktaax\Exception\ValidationException;
use Oktaax\Http\Request;
use Oktaax\Http\Response;

class ValidatorHandler
{

    public static function handle()
    {
        return function (Request $request, Response $response, $next) {

            try {
                $next();
            } catch (ValidationException $th) {
                //throw $th;
            }
        };
    }
}
