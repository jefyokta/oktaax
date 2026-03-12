<?php

namespace Oktaax\Http;

use Oktaax\Contracts\Middleware;
use Oktaax\Core\Application;
use Oktaax\Exception\HttpException;

class Router
{
    /**
     * method:url => Route
     */
    private static array $routeCache = [];

    /**
     * [method][path] => Route
     */
    private static array $routes = [];

    private static array $dynamicRoutes = [];

    private static array $globalMiddlewares = [];

    private static array $currentMiddleware = [];

    private function addRoute(string $path, string $method, string|callable|array $handler, array $middlewares)
    {
        $middlewares = [
            ...self::$globalMiddlewares,
            ...self::$currentMiddleware,
            ...$middlewares
        ];

        $route = new Route($path, $method, $handler, $middlewares);

        if ($route->isDynamic()) {
            self::$dynamicRoutes[$method][] = $route;
        } else {
            self::$routes[$method][$path] = $route;
        }
    }

    public function get(string $path, string|callable|array $callback, ...$middlewares)
    {
        $this->addRoute($path, "GET", $callback, $middlewares);
        return $this;
    }

    public function post(string $path, string|callable|array $callback, ...$middlewares)
    {
        $this->addRoute($path, "POST", $callback, $middlewares);
        return $this;
    }

    public function put(string $path, string|callable|array $callback, ...$middlewares)
    {
        $this->addRoute($path, "PUT", $callback, $middlewares);
        return $this;
    }

    public function delete(string $path, string|callable|array $callback, ...$middlewares)
    {
        $this->addRoute($path, "DELETE", $callback, $middlewares);
        return $this;
    }

    public function patch(string $path, string|callable|array $callback, ...$middlewares)
    {
        $this->addRoute($path, "PATCH", $callback, $middlewares);
        return $this;
    }

    public function options(string $path, string|callable|array $callback, ...$middlewares)
    {
        $this->addRoute($path, "OPTIONS", $callback, $middlewares);
        return $this;
    }

    public function head(string $path, string|callable|array $callback, ...$middlewares)
    {
        $this->addRoute($path, "HEAD", $callback, $middlewares);
        return $this;
    }

    public function use(...$middlewares)
    {
        self::$globalMiddlewares = [
            ...self::$globalMiddlewares,
            ...$middlewares
        ];

        return $this;
    }

    public function middleware($middlewares, callable $callback)
    {
        self::$currentMiddleware = is_array($middlewares)
            ? $middlewares
            : [$middlewares];

        $callback($this);

        self::$currentMiddleware = [];
    }

    public static function handle(Request $request)
    {
        $url = $request->uri;
        $method = $request->getMethod();

        $route = self::findHandler($url, $method);

        return $route->terminate(
            Application::getRequest(),
            Application::getResponse()
        );
    }

    public static function findHandler(string $url, string $method): Route
    {
        $key = $method . ':' . $url;

        if (isset(self::$routeCache[$key])) {
            return self::$routeCache[$key];
        }

        $routes = self::$routes[$method] ?? [];

        if (isset($routes[$url])) {
            return self::$routeCache[$key] = $routes[$url];
        }

        foreach (self::$dynamicRoutes[$method] ?? [] as $route) {
            if ($route->isMatch($url)) {
                return self::$routeCache[$key] = $route;
            }
        }

        throw new HttpException(404, "Not Found");
    }
}