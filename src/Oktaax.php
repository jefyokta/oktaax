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

namespace Oktaax;


use Error;
use Oktaax\Http\Middleware\Csrf as MiddlewareCsrf;
use Oktaax\Http\Request;
use Oktaax\Http\Response as OktaResponse;
use Oktaax\Interfaces\Server;
use ReflectionMethod;
use Swoole\Coroutine;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response;
use Swoole\Http\Server as HttpServer;

/**
 * 
 * A class to make application server
 * 
 * @package Oktaax
 * 
 * 
 */


class Oktaax implements Server
{

    /**
     * Swoole HTTP server instance.
     *
     * @var Swoole\Http\Server | Swoole\WebSocket\Server
     * 
     */

    protected HttpServer|null $server = null;
    /**
     * Server Settings
     * 
     * @var array
     */



    protected array $serverSettings = [];

    /**
     * Application route definitions.
     *
     * @var array
     */
    protected $route = [];

    /**
     * Global middleware stack.
     *
     * @var callable[]
     */
    protected $globalMiddleware = [];


    private OktaResponse $response;


    /**
     * Configuration settings for the application.
     *
     * @var array
     */

    protected  array $config = [
        "viewsDir" => "views/",
        "logDir" => "log",
        "render_engine" => null,
        "blade" => [
            "cacheDir" => null,
            "functionsDir" => null
        ],
        "useOktaMiddleware" => false,
        "sock_type" => null,
        "mode" => null,
        "publicDir" => "public",
        "app" => [
            "key" => null,
            "name" => "oktaax",
            "useCsrf" => false,
            "csrfExp" => (60 * 5)
        ]
    ];

    /**
     * Application host
     * 
     * @var string
     */
    protected $host;


    /**
     * Application port
     * 
     * @var int
     */
    protected $port;

    /**
     * 
     * Application's protocol
     * @var string
     * 
     */

    protected string $protocol = 'http';

    /**
     * 
     * Middleware path
     * @var array
     * 
     */
    private  array $pathMiddlewares;


    /** 
     * Set view configuration
     * 
     * @param string $viewDir
     * @param 'blade'|'php' $render_engine
     * 
     * @return static
     * 
     */
    public function setView($viewDir, $render_engine)
    {
        $this->config["viewDir"] = $viewDir;
        $this->config['render_engine'] = $render_engine;

        return $this;
    }

    /** 
     * Set blade  configuration
     * 
     * @param string $viewDir
     * @param string $cacheDir
     * @param string $functionDir
     * @return static
     * 
     */
    public function useBlade(
        $viewDir = "views/",
        $cachedir = "views/cache/",
        $functionDir = null
    ) {

        $this->config["viewDir"] = $viewDir;
        $this->config['render_engine'] = "blade";
        $this->config['blade']["cacheDir"] = $cachedir;
        $this->config['blade']["functionDir"] = $functionDir;
        return $this;
    }


    /**
     * Enable SSL for the server.
     *
     * @return $this
     */
    public function withSSL($cert, $key)
    {

        if (!file_exists($cert)) {
            throw new Error("Certificate not found!");
        }

        if (!file_exists($key)) {
            throw new Error("key not found!");
        }
        $this->setServer('ssl_cert_file', $cert);
        $this->setServer('ssl_key_file', $key);

        $this->config['sock_type'] = SWOOLE_SOCK_TCP | SWOOLE_SSL;
        $this->config['mode'] = SWOOLE_PROCESS;
        $this->protocol = 'https';

        return $this;
    }

    /**
     * Set SSL certificates for the server.
     *
     * @param string $cert The SSL certificate file path.
     * @param string $key The SSL key file path.
     */


    /**
     * Set the server \Swoole\Http\Server options.
     *
     * @param string|array $setting \Swoole\Http\Server settings.
     * @param mixed $value array value  
     *
     */
    public function setServer(array|string $setting, $value = null)
    {
        if (is_array($setting)) {
            if (!is_null($value)) {
                Console::warning("value would'nt be save");
                trigger_error(" value would'nt be save", E_USER_WARNING);
            }
            $this->serverSettings = $setting;
        } elseif (is_string($setting)) {
            $this->serverSettings[$setting] = $value;
        } else {
            throw new Error('$setting argument must ber array or string');
        }
    }


    /**
     * 
     * Enable csrf
     * 
     * @param string $appkey
     * 
     * @param int $expr
     * 
     */
    public function useCsrf(string $appkey, int $expr = 300)
    {

        $this->config['app']['key'] = $appkey;
        $this->config['app']['csrfExp'] = $expr;

        $this->config['app']['useCsrf'] = true;
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
        if (strpos($path, '{') === false) {
            $this->route[$path][$method] = [
                "action" => $handler,
                "middleware" => $middlewares,
                "isDynamic" => false
            ];
        } elseif (strpos($path, '{') !== false && strpos($path, '}') !== false) {
            $this->route[$path][$method] = [
                "action" => $handler,
                "middleware" => $middlewares,
                "isDynamic" => true
            ];
        } else {
            throw new Error("Dynamic route must has `{` and `}`");
        }
    }

    /**
     * Set configuration values for the application.
     *
     * @param string $key Configuration key.
     * @param mixed $value Configuration value.
     */
    public function set($key, $value)
    {
        $this->config[$key] = $value;
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

        $this->addroute($path, "POST", $callback, $middlewares);
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
     * Placeholder function for path. Can be used for future expansion.
     */
    public function path() {}



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
        if (isset($this->route[$route][$method])) {
            $handler = $this->route[$route][$method]['action'];
            $middlewares = $this->route[$route][$method]['middleware'];
            return ["route" => $route, "handler" => $handler, "middlewares" => $middlewares];
        }

        foreach ($this->route as $pattern => $methods) {
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

        return false; // Jika tidak ada route yang cocok
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
                    if (is_string($handler)) {
                        $class = explode("." || "@", $handler)[0];
                        $parts = preg_split("/[.@]/", $handler);
                        $class = $parts[0];
                        $method = $parts[1];
                        $initial = new $class;
                        call_user_func([$initial, $method], $request, $response, $param);
                    } elseif (is_callable($handler)) {
                        $handler($request, $response, $param);
                    } elseif (is_array($handler)) {

                        $class = $handler[0];
                        $method = $handler[1];

                        $reflection = new ReflectionMethod($class, $method);
                        if ($reflection->isStatic()) {
                            call_user_func([$class, $method], $request, $response, $param);
                        } else {
                            $instance = new $class;
                            call_user_func([$instance, $method], $request, $response, $param);
                        }
                    } else {
                        throw new Error("Handler must be type of string/callable/array");
                    }
                }
            ]);
            $this->runStackMidleware($middlewaresStack, $request, $response);
        } else {
            $response->status(404);
            $err = Coroutine::readFile(__DIR__ . "/Views/HttpError/index.php");
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
    private function runStackMidleware($stack, Request $request, $response, $param = null)
    {

        $next = function ($param = null) use (&$stack, $request, $response, &$next) {

            if (!empty($stack)) {
                $middleware = array_shift($stack);

                if (is_callable($middleware)) {
                    $middleware($request, $response, $next, $param);
                } elseif (is_string($middleware)) {
                    $parts = preg_split("/[.@]/", $middleware);
                    $class = $parts[0];
                    $method = $parts[1];
                    $initial = new $class;
                    call_user_func([$initial, $method], $request, $response, $next, $param);
                } elseif (is_array($middleware)) {


                    $class = $middleware[0];
                    if (!class_exists($class)) {
                        throw new Error("Class $class does'nt exist");
                    }
                    $method = $middleware[1] ?? throw new Error("Method not found!");
                    $reflection = new ReflectionMethod($class, $method);
                    if ($reflection->isStatic()) {
                        call_user_func([$class, $method], $request, $response, $param);
                    } else {
                        $instance = new $class;
                        call_user_func([$instance, $method], $request, $response, $param);
                    }
                } else {
                    throw new Error("Handler must be type of string/callable/array");
                }
            }
        };

        $next($param);
    }


    /**
     * Return an array of default middleware.
     *
     * @return array The default middleware array.
     */
    private function OktaaMiddlewares()
    {


        $log = function (Request $req, OktaResponse $res, $next) {

            try {


                go(function () use ($next) {

                    $next();
                });

                go(function () use ($req, $res) {
                    $method = $req->server['request_method'];
                    $path = $req->server['request_uri'];
                    $addres = $req->server['remote_addr'];
                    $date = date("d/m/Y");
                    $time = date("h:i");
                    $status = $res->status;

                    $text = "[$date $time] $addres:  $method $path.........[$status]\n";
                    Coroutine::writeFile($this->config['logDir'], $text, FILE_APPEND);
                    Console::log($text);
                });
            } catch (\Throwable $th) {
                go(function () use ($req, $th) {
                    $method = $req->server['request_method'];
                    $path = $req->server['request_uri'];
                    $addres = $req->server['remote_addr'];
                    $date = date("d/m/Y");
                    $time = date("h:i");
                    $text = "[$date $time] $addres: $method $path error " . $th->getMessage() . ":" . $th->getLine() . " in " . $th->getFile() . "\n";
                    Coroutine::writeFile($this->config['logDir'], $text, FILE_APPEND);
                    Console::error($text);
                });

                go(function () use ($res, $th) {
                    $res->status(500);
                    $res->response->end($th->getMessage());
                });
            }
        };


        return compact("log");
    }

    /**
     * 
     * Initialization server
     * 
     * 
     */
    private function init()
    {
        if (!is_null($this->config['mode']) && !is_null($this->config['sock_type'])) {
            $this->protocol = "https";
            $this->server = new HttpServer($this->host, $this->port, $this->config['mode'], $this->config['sock_type']);
        } else {
            $this->server = new HttpServer($this->host, $this->port,);
        }
    }



    /**
     * @param int $port
     * @param string|callback|null $hostOrcallback
     * @param callable|null $callback
     */

    public function listen($port,  $hostOrcallback = null, $callback = null)
    {
        $this->port = $port;
        $this->host = is_string($hostOrcallback) ? $hostOrcallback : "127.0.0.1";
        if (is_null($this->server)) {
            $this->init();
        }

        $this->server->set($this->serverSettings);


        !$this->config['useOktaMiddleware'] ?: $this->use($this->OktaaMiddlewares()['log']);

        if ($this->config['app']['useCsrf']) {
            $this->use(MiddlewareCsrf::generate($this->config['app']['key'], $this->config['app']['csrfExp']));
            $this->use(MiddlewareCsrf::handle($this->config['app']['key']));
        }
        if (is_callable($hostOrcallback)) {
            $hostOrcallback($this->protocol . "://" . $this->host . ":" . $this->port);
        } elseif (is_callable($callback) && !is_callable($hostOrcallback)) {
            $callback($this->protocol . "://" . $this->host . ":" . $this->port);
        }

        $this->onRequest();

        $this->server->start();
    }



    /**
     * Application on request event
     * 
     */
    protected function onRequest()
    {

        $this->server->on("request", function (SwooleRequest $request, Response $response) {
            $request = new Request($request);
            $response = new OktaResponse($response, $request, $this->config);
            $this->response = $response;
            $path = $request->request->server['request_uri'];
            $file = $this->config['publicDir'] . $path;
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
     * Add middleware for every spesific path
     * 
     * @param string $path
     * @param string|callable $middleware
     * 
     * @return void
     * 
     */
    public function useFor(string $path, callable| string|array $middleware)
    {
        $this->pathMiddlewares[$path][] = $middleware;
    }

    /**
     * 
     * Add middlware from $this->pathMiddlewares to spesific route
     * 
     */
    private function handlerPathMiddleware()
    {
        foreach ($this->pathMiddlewares as $path => $value):
            foreach ($this->route as $route => &$val):
                if ($route === $path || strpos($route, $path . "/") === 0):
                    foreach ($val as &$method):
                        foreach ($value as $key => $middleware) :
                            if (!in_array($middleware, $method['middleware'])) :
                                if (is_array($method['middleware'])) :
                                    array_unshift($method['middleware'], $middleware);
                                else:
                                    $method['middleware'][] = $middleware;
                                endif;
                            endif;
                        endforeach;
                    endforeach;
                endif;
            endforeach;
        endforeach;
    }
};
