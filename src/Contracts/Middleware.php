<?php

namespace Oktaax\Contracts;

use Oktaax\Http\Request;
use Oktaax\Http\Response;

interface Middleware
{
    public function handle(Request $request, Response $response, $next);
}
