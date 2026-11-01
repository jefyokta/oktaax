<?php

namespace Oktaax\Http\Routing;

use Oktaax\Http\Request;
use Oktaax\Http\Response;

use FastRoute\Dispatcher;
use FastRoute\Dispatcher\GroupCountBased;
use Oktaax\Contracts\Middleware;
use Oktaax\Exception\HttpException;

class Router
{
    private RouteCollector $collector;
    private ?GroupCountBased $dispatcher = null;
    private array $globalMiddlewares = [];
    private array $currentMiddleware = [];
    private bool $frozen = false;

    private function assertMutable(): void
    {
        if ($this->frozen) {
            throw new \LogicException(
                'Routes and middleware are frozen after boot.',
            );
        }
    }

    public function freeze(): void
    {
        $this->compile();
        $this->frozen = true;
    }

    public function __construct()
    {
        $this->collector = new RouteCollector(function () {
            $this->assertMutable();
            $this->invalidate();
        });
    }

    /** Collector mutations invalidate the compiled dispatcher automatically. */
    public function collector(): RouteCollector
    {
        $this->assertMutable();
        $this->invalidate();
        return $this->collector;
    }

    public function invalidate(): void
    {
        $this->dispatcher = null;
    }

    public function compile(): GroupCountBased
    {
        return $this->dispatcher ??= new GroupCountBased(
            $this->collector->getData(),
        );
    }

    private function addRoute(
        string $path,
        string $method,
        string|callable|array $handler,
        array $middlewares,
    ): void {
        $this->assertMutable();
        $route = new Route($path, $handler, [
            ...$this->globalMiddlewares,
            ...$this->currentMiddleware,
            ...$middlewares,
        ]);
        $this->collector->addRoute($method, $path, $route);
        $this->invalidate();
    }

    public function get(
        string $path,
        string|callable|array $callback,
        mixed ...$middlewares,
    ): static {
        $this->addRoute($path, 'GET', $callback, $middlewares);
        return $this;
    }

    public function post(
        string $path,
        string|callable|array $callback,
        mixed ...$middlewares,
    ): static {
        $this->addRoute($path, 'POST', $callback, $middlewares);
        return $this;
    }

    public function put(
        string $path,
        string|callable|array $callback,
        mixed ...$middlewares,
    ): static {
        $this->addRoute($path, 'PUT', $callback, $middlewares);
        return $this;
    }

    public function delete(
        string $path,
        string|callable|array $callback,
        mixed ...$middlewares,
    ): static {
        $this->addRoute($path, 'DELETE', $callback, $middlewares);
        return $this;
    }

    public function patch(
        string $path,
        string|callable|array $callback,
        mixed ...$middlewares,
    ): static {
        $this->addRoute($path, 'PATCH', $callback, $middlewares);
        return $this;
    }

    public function options(
        string $path,
        string|callable|array $callback,
        mixed ...$middlewares,
    ): static {
        $this->addRoute($path, 'OPTIONS', $callback, $middlewares);
        return $this;
    }

    public function head(
        string $path,
        string|callable|array $callback,
        mixed ...$middlewares,
    ): static {
        $this->addRoute($path, 'HEAD', $callback, $middlewares);
        return $this;
    }

    /** Global middleware applies to subsequently registered routes. */
    public function use(mixed ...$middlewares): static
    {
        $this->assertMutable();
        $this->globalMiddlewares = [
            ...$this->globalMiddlewares,
            ...$middlewares,
        ];
        return $this;
    }

    /**
     * Register a scoped middleware group and restore its previous state even when registration throws.
     *
     * @param callable(self): mixed $callback
     */
    public function middleware(
        callable|array|Middleware $middlewares,
        callable $callback,
    ): static {
        $this->assertMutable();
        $previous = $this->currentMiddleware;
        $group =
            is_callable($middlewares) || $middlewares instanceof Middleware
                ? [$middlewares]
                : $middlewares;
        $this->currentMiddleware = [...$previous, ...$group];

        try {
            $callback($this);
        } finally {
            $this->currentMiddleware = $previous;
        }

        return $this;
    }

    /**
     * Resolve a path/method pair, including HEAD fallback; does not emit a response.
     *
     * @return array{0: \Oktaax\Http\Routing\Route|callable(\Oktaax\Http\Request, \Oktaax\Http\Response): mixed, 1: array<string, string>}
     * @throws \Oktaax\Exception\HttpException For unmatched paths or unsupported methods; 405 includes Allow.
     */
    public function resolve(string $url, string $method): array
    {
        $result = $this->compile()->dispatch(strtoupper($method), $url);

        if ($result[0] === Dispatcher::NOT_FOUND) {
            throw new HttpException(404, 'Not Found');
        }
        if ($result[0] === Dispatcher::METHOD_NOT_ALLOWED) {
            throw new HttpException(405, 'Method Not Allowed', [
                'Allow' => implode(', ', $result[1]),
            ]);
        }

        return [$result[1], $result[2]];
    }

    public function dispatch(Request $request, Response $response): mixed
    {
        [$route, $params] = $this->resolve(
            $request->path(),
            $request->getMethod(),
        );
        $request->params = $params;
        return $route($request, $response);
    }
}
