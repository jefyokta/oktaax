<?php

namespace Oktaax\Http;

use Oktaax\Core\Application;
use Oktaax\Exception\HttpException;
use Swoole\Http\Server;

class Router
{
    /**
     * Route Cache
     * @var Route[]
     */
    private static $routeCache = [];
    /**
     * Summary of routes
     * @var Route[]
     */
    private static $routes = [];
    private function addRoute(string $path, string $method, string|callable|array $handler, array $middlewares)
    {

        self::$routes[$path] = new Route($path, $method, $handler, $middlewares);
    }


    /**
     * Define a GET route.
     *
     * @param string $path The route path.
     * @param string|callable|array $callback The route handler.
     * @param string|callable|array  $middleware Route specific middleware.
     * @return static

     */
    public function get(string $path, string|callable|array $callback, string|callable|array ...$middlewares)
    {

        $this->addRoute($path, "GET", $callback, $middlewares);
        return $this;
    }

    /**
     * Define a POST route.
     *
     * @param string $path The route path.
     * @param string|callable|array $callback The route handler.
     * @param callable[] $middleware Route specific middleware.
     * 
     * @return static
     */
    public function post(string $path, string|callable|array $callback, string|callable|array ...$middlewares)
    {
        $this->addRoute($path, "POST", $callback, $middlewares);
        return $this;
    }


    /**
     * Define a PUT route.
     *
     * @param string $path The route path.
     * @param string|callable|array $callback The route handler.
     * @param callable[] $middleware Route specific middleware.
     */
    public function put(string $path, string|callable|array $callback, string|callable|array ...$middlewares)
    {

        $this->addRoute($path, "PUT", $callback, $middlewares);
        return $this;
    }



    /**
     * Define a DELETE route.
     *
     * @param string $path The route path.
     * @param string|callable|array $callback The route handler.
     * @param callable[] $middleware Route specific middleware.
     * 
     *  
     * @return static

     */
    public function delete(string $path, string|callable|array $callback, string|callable|array ...$middlewares)
    {
        $this->addRoute($path, "DELETE", $callback, $middlewares);
        return $this;
    }



    /**
     * Define a PATCH route.
     *
     * @param string $path The route path.
     * @param string|callable|array $callback The route handler.
     * @param callable[] $middleware Route specific middleware.
     * 
     * @return static

     */
    public function patch(string $path, string|callable|array $callback, string|callable|array ...$middlewares)
    {

        $this->addRoute($path, "PATCH", $callback, $middlewares);
        return $this;
    }


    /**
     * Define a OPTIONS route.
     *
     * @param string $path The route path.
     * @param string|callable|array $callback The route handler.
     * @param callable[] $middleware Route specific middleware.
     * 
     *     
     *  * @return static

     */
    public function options(string $path, string|callable|array $callback, string|callable|array ...$middlewares)
    {

        $this->addRoute($path, "OPTIONS", $callback, $middlewares);
        return $this;
    }

    /**
     * Define a HEAD route.
     *
     * @param string $path The route path.
     * @param string|callable|array $callback The route handler.
     * @param callable[] $middleware Route specific middleware.
     * 
     * @return static
     */
    public function head(string $path, string|callable|array $callback, string|callable|array ...$middlewares)
    {
        $this->addroute($path, "HEAD", $callback, $middlewares);
        return $this;
    }

    public static function handle(Request $request)
    {
        $url =  $request->uri;
        $method = $request->getMethod();

        $routeHandler =   self::findHandler($url, $method);
        return  $routeHandler->terminate(Application::getRequest(), Application::getResponse());
    }

    public static function findHandler(string $url, string $method): Route
    {
        $key = (string)$method . ':' . $url;

        if (isset(self::$routeCache[$key])) {
            return self::$routeCache[$key];
        }

        $routeHandler = array_find(self::$routes, function (Route $route) use ($method, $url) {
            return $route->isMatch($url, $method);
        });

        if (!$routeHandler) {
            throw new HttpException(404, "Not Found");
        }

        self::$routeCache[$key] = $routeHandler;

        return $routeHandler;
    }
}
