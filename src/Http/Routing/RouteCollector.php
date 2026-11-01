<?php

namespace Oktaax\Http\Routing;

use Closure;
use FastRoute\DataGenerator\GroupCountBased;
use FastRoute\RouteParser\Std;

/** Keeps low-level collector mutations subject to the router's lifecycle. */
final class RouteCollector extends \FastRoute\RouteCollector
{
    public function __construct(private Closure $beforeMutation)
    {
        parent::__construct(new Std(), new GroupCountBased());
    }

    public function addRoute($httpMethod, $route, $handler)
    {
        ($this->beforeMutation)();
        parent::addRoute($httpMethod, $route, $handler);
    }
}
