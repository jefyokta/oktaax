<?php

namespace Oktaax\Overload;

use Oktaax\Interfaces\Middleware;

class GlobalMiddleware
{
    private $middlewares = [];

    /**
     * @param class-string<Middleware> $middleware
     */
    public function use($middleware)
    {
        if (is_scalar($middleware)) {
            $middleware = [new $middleware, "handle"];
        }
        $this->middlewares[] = $middleware;
    }

    public function getMiddlewares()
    {
        return $this->middlewares;
    }
}
