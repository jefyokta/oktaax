<?php

namespace Oktaax\Http\Routing;

use Oktaax\Http\Request;
use Oktaax\Http\Response;

final class Route
{
    private Pipeline $pipeline;

    public function __construct(
        private string $path,
        string|callable|array $handler,
        array $middlewares = [],
    ) {
        $this->pipeline = new Pipeline(...[...$middlewares, $handler]);
    }

    public function __invoke(Request $request, Response $response): mixed
    {
        return ($this->pipeline)($request, $response);
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
