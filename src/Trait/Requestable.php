<?php

/**
 * Oktaax - Real-time Websocket and HTTP Server using Swoole
 *
 * @package Oktaax
 * @author Jefyokta
 * @license MIT License
 * 
 * @link https://github.com/jefyokta/oktaax
 *
 * @copyright Copyright (c) 2024, Jefyokta
 *
 * MIT License
 *
 * Copyright (c) 2024 Jefyokta
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 */




namespace Oktaax\Trait;


use Error;
use Oktaax\Oktaax;
use ReflectionMethod;
use Oktaax\Http\Request;
use OpenSwoole\Http\Response;
use Oktaax\Error\CombineException;
use Oktaax\Http\Response as OktaResponse;
use OpenSwoole\Http\Server as HttpServer;
use OpenSwoole\Http\Request as SwooleRequest;


trait Requestable
{

    /**
     * Application route definitions.
     *
     * @var array
     */
    protected $routes = [];

    public string $controller_namespace = "Appx\\Controller\\";
    public string $middleware_namespace = "Appx\\Middleware\\";



    /**
     * Global middleware stack.
     *
     * @var callable[]
     */
    protected $globalMiddleware = [];




    /**
     * Application on request event
     * 
     */
    protected function onRequest()
    {

        $this->server->on("request", function (SwooleRequest $request, Response $response) {
            $request = new Request($request);
            $response = new OktaResponse($response, $request, $this->config);
            $path = $request->server['request_uri'];
            $file = $this->config->publicDir . $path;
            if (is_file($file) && file_exists($file)) {
                $extension = pathinfo($file, PATHINFO_EXTENSION);
                $types = require __DIR__ . "/Utils/MimeTypes.php";
                $mimetype = $types[$extension] ?? "application/octet-stream";
                $response->header("Content-Type", $mimetype);
                $response->sendfile($file);
            } else {
                $this->AppHandler($request, $response);
            }
        });
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


    /**
     * Register a global middleware.
     *
     * @param callable $globalMiddleware The middleware callback.
     * @return static
     */

    public function use(callable $globalMiddleware)
    {
        $this->globalMiddleware[] = $globalMiddleware;

        return $this;
    }



    /**
     * 
     * Registering Application Routes
     * @param string $path
     * @param  Oktaax $app
     */
    public function path(string $path, Oktaax $app)
    {

        $routes =  $app->getRoutes();
        foreach ($routes as $route => $methods) {
            foreach ($methods as $method => $handler) {
                $this->routes[str_ends_with($path, '/') ? $path : $path . "/" . $route][$method] = $handler;
            }
        }

        return $this;
    }



    /**
     * Handle the incoming request and route it.
     *
     * @param \Oktaax\Http\Request $request The HTTP request.
     * @param \Oktaax\Http\Response $response The HTTP response.
     */

    private function AppHandler(Request $request, OktaResponse $response)
    {


        $path = $request->server['request_uri'];
        $path = filter_var($path, FILTER_SANITIZE_URL);
        $reqmethod = ["PUT", "DELETE", "OPTIONS", "PATCH"];

        if ($request->server['request_method'] === "POST") {
            $method = strtoupper($request->post("_method") ?? "POST");
            if (!in_array($method, $reqmethod)) {
                $method = "POST";
            }
        } else {
            $method = $request->server['request_method'];
        }

        if (!empty($this->pathMiddlewares)) {
            $this->handlerPathMiddleware();
        }

        $stack =  array_merge($this->globalMiddleware, [
            function ($request, $response, $next) use ($path, $method) {
                $this->proccesRequest($request, $response, $method, $path, $next);
            }
        ]);


        $this->runStackMidleware($stack, $request, $response);
    }

    /**
     * 
     * Filter url before calling action
     * 
     * @param string $route
     * @param string $method
     * @param \Oktaax\Http\Request &$request
     * @return array
     * 
     */
    private function matchRoute(string $route, string $method, Request &$request)
    {
        $route = rtrim($route, '');
        if (isset($this->routes[$route][$method])) {
            $handler = $this->routes[$route][$method]['action'];
            $middlewares = $this->routes[$route][$method]['middleware'];
            return ["route" => $route, "handler" => $handler, "middlewares" => $middlewares];
        }

        foreach ($this->routes as $pattern => $methods) {
            if (isset($methods[$method]) && $methods[$method]['isDynamic']) {
                $regex = preg_replace('/\{([a-zA-Z_]+)\}/', '([^/]+)', str_replace('/', '\/', $pattern));

                $regex = "#^$regex$#";


                if (preg_match($regex, $route, $matches)) {
                    array_shift($matches);

                    if (strpos($pattern, '{') !== false) {
                        preg_match_all('/\{([a-zA-Z_]+)\}/', $pattern, $paramNames);
                        $request->params = array_combine($paramNames[1], $matches);
                    }

                    $handler = $methods[$method]['action'];
                    $middlewares = $methods[$method]['middleware'];
                    return ["route" => $pattern, "handler" => $handler, "middlewares" => $middlewares];
                }
            }
        }

        return false;
    }




    /**
     * Process the request and invoke the appropriate route handler.
     *
     * @param \Oktaax\Http\Request $request The HTTP request.
     * @param \Oktaax\Http\Response $response The HTTP response.
     * @param string $method The HTTP method.
     * @param string $path The request path.
     * @param callable $next The next middleware function.
     * 
     * 
     */
    private function proccesRequest(Request $request, OktaResponse $response, string $method, string $path, $next)
    {
        $match = $this->matchRoute($path, $method, $request);

        if ($match !== false) {
            $handler = $match['handler'];
            $middlewares = $match['middlewares'];

            $middlewaresStack = array_merge($middlewares, [
                function ($request, $response, $next, $param) use ($handler) {
                    if (is_callable($handler)) {
                        $handler($request, $response, $param);
                    } else {
                        $parts = $handler;
                        if (is_string($handler)) {
                            $parts = preg_split("/[.@]/", $handler);
                        }

                        $class = $parts[0];
                        $method = $parts[1] ?? throw new Error("Method not found!");

                        if (! class_exists($class)) {
                            if (!class_exists($class = $this->controller_namespace . $class)) throw new \Exception("Class {$class} not found!", 1);
                        }

                        $reflection = new ReflectionMethod($class, $method);
                        if (! $reflection->isStatic()) {
                            $class = new $class;
                        }
                        call_user_func([$class, $method], $request, $response, $param);
                    }
                }
            ]);
            $this->runStackMidleware($middlewaresStack, $request, $response);
        } else {
            $response->status(404);
            ob_start();
            require __DIR__ . "/../Views/HttpError/index.php";
            $err = ob_get_clean();
            $response->response->end($err);
        }
    }


    /**
     * Run a stack of middleware functions sequentially.
     *
     * @param callable[] $stack The middleware stack.
     * @param \Oktaax\Http\Request $request The HTTP request.
     * @param \Oktaax\Http\Response $response The HTTP response.
     * @param array $param Optional parameters to pass to middleware.
     */
    private function runStackMidleware(array $stack, \Oktaax\Http\Request $request, \Oktaax\Http\Response $response, mixed $param = null)
    {

        $next = function ($param = null) use (&$stack, $request, $response, &$next) {

            if (!empty($stack)) {
                $middleware = array_shift($stack);
                if (is_callable($middleware)) {
                    $middleware($request, $response, $next, $param);
                } else {
                    if (is_string($middleware)) {
                        $middleware = preg_split("/[.@]/", $middleware);
                    }
                    $class = $middleware[0];
                    $method = $middleware[1];
                    if (!class_exists($class) && is_string($middleware)) {
                        $class = $this->middleware_namespace . $class;
                    }
                    $reflection = new ReflectionMethod($class, $method);
                    $instance = $reflection->isStatic() ? $class : new $class;
                    call_user_func([$instance, $method], $request, $response, $param);
                }
            }
        };

        $next($param);
    }

    /**
     * 
     * Initialization server
     * 
     * 
     */
    private function init()
    {
        if (!is_null($this->config->mode) && !is_null($this->config->sock_type)) {
            $this->protocol = "https";
            $this->server = new HttpServer($this->host, $this->port, $this->config->mode, $this->config->sock_type);
        } else {
            $this->server = new HttpServer($this->host, $this->port);
        }
    }

    /**
     * 
     * Registering Application Routes
     * @param string $path
     * @param string|callable|array $handler
     * @param callable|array|string ...$middlewares
     */

    private function addRoute(string $path, string $method, string|callable|array $handler, array $middlewares)
    {

        if (method_exists($this, 'bootLaravel')) {
            throw new CombineException("You can't add route if you using Laravelable and Requestable at the same time");
        }

        if (strpos($path, '{') === false) {
            $this->routes[$path][$method] = [
                "action" => $handler,
                "middleware" => $middlewares,
                "isDynamic" => false
            ];
        } elseif (strpos($path, '{') !== false && strpos($path, '}') !== false) {
            $this->routes[$path][$method] = [
                "action" => $handler,
                "middleware" => $middlewares,
                "isDynamic" => true
            ];
        } else {
            throw new Error("Dynamic route must has `{` and `}`");
        }
    }
}
