<?php

namespace Oktaax\Http;

use Oktaax\Core\Application;
use Oktaax\Core\Promise\Asynchronous;
use Oktaax\Utils\AsyncTransform;
use Oktaax\Utils\Invoker;

final class Route
{
    private bool $dynamic = false;
    private string $pattern;
    private array $paramNames = [];


    /** @var callable */
    private $handler;

    /** @var callable[] */
    private array $middlewares = [];

    public function __construct(
        private string $path,
        private string $method,
        callable $handler,
        array $middlewares = []
    ) {
        $this->handler = AsyncTransform::hasAsyncAttribute($handler) ? new Asynchronous($handler) : $handler;
        $this->middlewares = $middlewares;

        $this->compile();
    }

    private function compile(): void
    {
        if (!str_contains($this->path, '{')) {
            $this->pattern = $this->path;
            return;
        }

        $this->dynamic = true;

        $pattern = preg_replace_callback(
            '/\{([^}]+)\}/',
            function ($match) {
                $this->paramNames[] = $match[1];
                return '([^/]+)';
            },
            $this->path
        );

        $this->pattern = "~^{$pattern}$~";
    }

    public function isDynamic()
    {
        return $this->dynamic;
    }

    public function match(string $url, string $method = ''): array|false
    {
        if (!$this->dynamic) {
            return ($this->path === $url && $this->method === strtoupper($method))
                ? []
                : false;
        }

        if (!preg_match($this->pattern, $url, $matches)) {
            return false;
        }

        array_shift($matches);

        return array_combine($this->paramNames, $matches);
    }

    public function terminate(Request $request, Response $response, array $params = [])
    {
        $request->params = $params;

        $count = \count($this->middlewares);
        $index = 0;

        $next = function () use ($request, $response, &$index, &$count, &$next) {
            if ($index >= $count) {
                return ($this->handler)($request, $response);
            }
            $cb = $this->middlewares[$index++];
            return $cb($request, $response, $next);
        };

        return $next();
    }


    public function getPath()
    {
        return $this->path;
    }
}
