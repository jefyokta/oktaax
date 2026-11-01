<?php

namespace Oktaax\Application;

use Oktaax\Application;
use Oktaax\Http\Request;
use Oktaax\Http\Response;

final class RequestContext
{
    public function __construct(
        public readonly Application $application,
        public readonly Request $request,
        public readonly Response $response,
    ) {}

    public function scope(): RequestScope
    {
        return $this->request->scope();
    }
}
