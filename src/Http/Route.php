<?php

namespace Oktaax\Http;

use Oktaax\Core\Application;
use Oktaax\Core\Promise\Promise;
use Oktaax\Utils\Invoker;

final class Route
{
    private bool $dynamic = false;
    private string $pattern;
    private array $paramNames = [];

    private Invoker $invoker;

    private CallableWrapper $handler;
    private array $middlewares = [];

    public function __construct(
        private string $path,
        private string $method,
        mixed $handler,
        array $middlewares = []
    ) {
        $this->handler = new CallableWrapper($handler);

        $this->middlewares = array_map(
            fn($m) => new CallableWrapper($m, true),
            $middlewares
        );

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
        $stack = $this->middlewares;
        $stack[] = $this->handler;

        $request->params = $params;

        $next = function () use (&$stack, $request, $response, &$next) {

            if (empty($stack)) {
                return null;
            }

            /** @var CallableWrapper $cb */
            $cb = array_shift($stack);

            $r = $this->callHandler($cb, $request, $response, $next);
            return $r;
        };

        $result = $next();


        return $result;
    }

    private function callHandler(
        CallableWrapper $handler,
        Request $request,
        Response $response,
        callable $next
    ) {
        if ($handler->isAsync()) {
            Application::context()->set('__async', true);
        }

        if ($handler->isMiddleware()) {
            return $handler($request, $response, $next);
        }

        $result = $this->invoker
            ->addContext($request)
            ->addContext($response)
            ->addContext(Application::context())
            ->setPositional([$request, $response, $request->params])
            ->call($handler);

        return $result;
    }

    public function isAsync(): bool
    {
        return $this->handler->isAsync();
    }

    function getPath(){
        return $this->path;
    }
}
