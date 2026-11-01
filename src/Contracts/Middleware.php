<?php

namespace Oktaax\Contracts;

use Oktaax\Http\Request;
use Oktaax\Http\Response;

interface Middleware
{
    /**
     * Run the next stage or return/send a response to short circuit the pipeline.
     *
     * @param callable(): mixed $next
     * @return mixed
     */
    public function handle(Request $request, Response $response, $next);
}
