<?php


namespace Oktaax\Interfaces;

use Oktaax\Http\Request;
use Oktaax\Http\Response;

interface Middleware
{
    public static function handle(Request $request, Response $response, $next);
};
