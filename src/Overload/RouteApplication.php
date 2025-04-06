<?php

namespace Oktaax\Overload;

use Oktaax\Oktaax;

class RouteApplication
{
    private $route = [];
    public function use(string $route, Oktaax $app)
    {
        $this->route[$route] = $app;
    }
    function getRoute()
    {
        return $this->route;
    }
}
