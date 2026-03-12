<?php

namespace Oktaax\Http;

use Oktaax\Console;
use Oktaax\Contracts\Middleware;
use Oktaax\Utils\Invoker;

class Route
{
    private array $parameters = [];

    private bool $dynamic = false;

    private string $pattern;
    private Invoker $invoker;

    private array $paramNames = [];

    public function __construct(
        private string $path,
        private string $method,
        private $handler,
        private array $middlewares = []
    ) {
        $this->compile();
        $this->invoker = new Invoker();
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

        $this->pattern = "#^{$pattern}$#";
    }

    public function isDynamic(): bool
    {
        return $this->dynamic;
    }

    public function isMatch(string $url, string $method = ''): bool
    {
        if (!$this->dynamic) {
            return $this->path === $url && $this->method == strtoupper($method);
        }

        if (!preg_match($this->pattern, $url, $matches)) {
            return false;
        }

        array_shift($matches);

        $this->parameters = array_combine($this->paramNames, $matches);

        return true;
    }

    public function terminate(Request $request, Response $response)
    {
        $stack = [...$this->middlewares, $this->handler];

        $request->params = $this->parameters;

        $next = function () use (&$stack, $request, $response, &$next) {
            if (empty($stack)) {
                return;
            }

            $cb = array_shift($stack);

            return $this->callHandler($cb, $request, $response, $next);
        };

        return $next();
    }
    private function callHandler($handler, Request $request, Response $response, $next)
    {

        if (is_subclass_of($handler, Middleware::class)) {
            if (\is_string($handler)) {
                $handler = new $handler();
            }
            return $handler->handle($request, $response, $next);
        }

        return $this->invoker
            ->addContext($request)
            ->addContext($response)
            ->setPositional([$this->parameters])
            ->call($handler);
    }

    public function getPath()
    {
        return $this->path;
    }
}
