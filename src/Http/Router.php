<?php

namespace Oktaax\Http;

use FastRoute\DataGenerator\MarkBased;
use FastRoute\Dispatcher\GroupCountBased;
use FastRoute\RouteCollector;
use FastRoute\RouteParser\Std;
use Oktaax\Exception\HttpException;

class Router
{

    /**
     * [method][path] => Route
     */
    private static array $routes = [];

    private static array $dynamicRoutes = [];

    private static array $globalMiddlewares = [];

    private static array $currentMiddleware = [];

    private static ?RouteCollector $collector=null;

    public function collector()
    {
        if (! self::$collector) {
            self::$collector = new RouteCollector(new Std, new MarkBased);
        }
        return self::$collector;
    }

    private function addRoute(string $path, string $method, string|callable|array $handler, array $middlewares)
    {
        $middlewares = [
            ...self::$globalMiddlewares,
            ...self::$currentMiddleware,
            ...$middlewares
        ];

        self::collector()->addRoute($method, $path, new CallStack(...[...$middlewares, $handler]));
    }

    public function get(string $path, string|callable|array $callback, mixed ...$middlewares)
    {
        $this->addRoute($path, "GET", $callback, $middlewares);
        return $this;
    }

    public function post(string $path, string|callable|array $callback, mixed ...$middlewares)
    {
        $this->addRoute($path, "POST", $callback, $middlewares);
        return $this;
    }

    public function put(string $path, string|callable|array $callback, mixed ...$middlewares)
    {
        $this->addRoute($path, "PUT", $callback, $middlewares);
        return $this;
    }

    public function delete(string $path, string|callable|array $callback, mixed ...$middlewares)
    {
        $this->addRoute($path, "DELETE", $callback, $middlewares);
        return $this;
    }

    public function patch(string $path, string|callable|array $callback, mixed ...$middlewares)
    {
        $this->addRoute($path, "PATCH", $callback, $middlewares);
        return $this;
    }

    public function options(string $path, string|callable|array $callback, mixed ...$middlewares)
    {
        $this->addRoute($path, "OPTIONS", $callback, $middlewares);
        return $this;
    }

    public function head(string $path, string|callable|array $callback,mixed ...$middlewares)
    {
        $this->addRoute($path, "HEAD", $callback, $middlewares);
        return $this;
    }

    public function use(mixed ...$middlewares)
    {
        self::$globalMiddlewares = [
            ...self::$globalMiddlewares,
            ...$middlewares
        ];

        return $this;
    }

    public function middleware(callable |array $middlewares, callable $callback)
    {
        self::$currentMiddleware = \is_callable($middlewares)
            ? [$middlewares]
            : $middlewares;

        $callback($this);

        self::$currentMiddleware = [];
        return $this;
    }

    public static function handle(Request $request, Response $response)
    {
        $url = $request->uri;
        $method = $request->getMethod();
        $dispatcher = new GroupCountBased(self::$collector->getData());

        [$stat, $handler, $param] =  $dispatcher->dispatch($method, $url);
        if ($stat !== \FastRoute\Dispatcher::FOUND) {
            throw new HttpException(statusCode: 404, message: "Not Found");
        }
        $request->params = $param;
        return $handler($request, $response);

    }

    /**
     * Find matching route handler
     *
     * @param string $url
     * @param string $method
     * @return array{0:Route,1:array<string,string>}
     */
    public static function findHandler(string $url, string $method): array
    {
        if (isset(self::$routes[$method][$url])) {
            return [self::$routes[$method][$url], []];
        }

        foreach (self::$dynamicRoutes[$method] ?? [] as $route) {
            $params = $route->match($url, $method);
            if ($params !== false) {
                return [$route, $params];
            }
        }

        throw new HttpException(
            statusCode: 404,
            message: "Not Found"
        );
    }


}
