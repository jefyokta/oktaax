<?php

namespace Oktaax\Overload;

class GlobalMiddleware
{
    private $middlewares = [];
    public function use($middleware)
    {
        $this->middlewares[] = $middleware;
    }

    public function getMiddlewares()
    {
        return $this->middlewares;
    }
}
