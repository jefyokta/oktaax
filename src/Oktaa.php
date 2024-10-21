<?php

namespace Oktaax;

use Error;
use Oktaax\Http\Request;
use Oktaax\Http\Response as OktaResponse;
use Oktaax\Middleware\Csrf;
use ReflectionMethod;
use Swoole\Coroutine;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response;
use Swoole\Http\Server;
use Swoole\WebSocket\Server as WebSocketServer;

class Oktaa
{
    /**
     * Swoole HTTP server instance.
     *
     * @var Swoole\Http\Server | Swoole\WebSocket\Server
     * 
     */


    private Server| WebSocketServer|null $server = null;
    /**
     * Server Settings
     * 
     * @var array
     */


    private array $serverSettings = [];

    /**
     * Application route definitions.
     *
     * @var array
     */
    protected $route;

    /**
     * Global middleware stack.
     *
     * @var callable[]
     */
    protected $globalMiddleware = [];


    /**
     * Configuration settings for the application.
     *
     * @var array
     */

    private array $config = [
        "viewsDir" => "views/",
        "logDir" => "log",
        "render_engine" => null,
        "blade" => [
            "cacheDir" => null
        ],
        "useOktaMiddleware" => true,
        "sock_type" => null,
        "mode" => null,
        "withWebsocket" => false,
        "publicDir" => "public"
    ];

    /**
     * SSL certificate configuration.
     *
     * @var array
     */
    private array $certificate;

    /**
     * Application host
     * 
     * @var string
     */
    private $host;


    /**
     * Application port
     * 
     * @var int
     */
    private $port;

    /**
     * Oktaa constructor.
     *
     * @param string $host The server host.
     * @param int $port The server port.
     * @param array $options Configuration options.
     */


    private string $protocol = 'http';

    /**
     * 
     * Middleware path
     * @var array
     * 
     */
    private  array $pathMiddlewares;



    public function __construct(string $host, int $port)
    {
        $this->host = $host;
        $this->port = $port;;
    }
    /**
     * Enable SSL for the server.
     *
     * @return $this
     */
    public function withSSL()
    {
        Coroutine::run(function () {
            if (!empty($this->certificate)) {
                $cert = Coroutine::readFile($this->certificate[0]);
                $key = Coroutine::readFile($this->certificate[1]);

                if (!$cert) {
                    throw new Error("Failed to read SSL certificate file: " . $this->certificate[0]);
                }
                if (!$key) {
                    throw new Error("Failed to read SSL key file: " . $this->certificate[1]);
                }

                $this->serverSettings = [
                    'ssl_cert_file' => $this->certificate[0],
                    'ssl_key_file' => $this->certificate[1],
                    'ssl_ciphers' => 'ECDHE-RSA-AES128-GCM-SHA256:ECDHE-RSA-AES256-GCM-SHA384:!aNULL:!eNULL:!SSLv2:!SSLv3',
                    'ssl_protocols' => SWOOLE_TLSv1_2_METHOD,
                    'ssl_verify_peer' => false,
                    'ssl_sni_certs' => [
                        "jepi.okta" => [
                            'ssl_cert_file' => $this->certificate[0],
                            'ssl_key_file' =>  $this->certificate[1],
                        ],
                    ],
                    'trace_flags' => SWOOLE_TRACE_ALL,
                ];
                $this->config['sock_type'] = SWOOLE_SOCK_TCP | SWOOLE_SSL;
                $this->config['mode'] = SWOOLE_PROCESS;
                $this->protocol = 'https';
            } else {
                throw new Error('No certificate found! Please insert your certificates first using Oktaa::setCertificate().');
            }
        });
    }

    /**
     * Set SSL certificates for the server.
     *
     * @param string $cert The SSL certificate file path.
     * @param string $key The SSL key file path.
     */
    public function setCertificate($cert, $key)
    {

        $this->certificate = [$cert, $key];
    }

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
                Console::warning("value wouldnt be save");
                trigger_error(" value wouldnt be save", E_USER_WARNING);
            }
            $this->serverSettings = $setting;
        } elseif (is_string($setting)) {
            $this->serverSettings[$setting] = $value;
        } else {
            throw new Error('$setting argument must ber array or string');
        }
    }

    private function route(string $path, string $method, string|callable|array $handler, array $middlewares)
    {

        $this->route[$path][$method] = [
            "action" => $handler,
            "middleware" => $middlewares
        ];
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
     * @param callable[] $middleware Route specific middleware.
     */
    public function get(string $path, string|callable|array $callback, array $middleware = [])
    {

        $this->route($path, "GET", $callback, $middleware);
    }

    /**
     * Define a POST route.
     *
     * @param string $path The route path.
     * @param string|callable|array $callback The route handler.
     * @param callable[] $middleware Route specific middleware.
     */
    public function post(string $path, string|callable|array $callback, array $middleware = [])
    {

        $this->route($path, "POST", $callback, $middleware);
    }


    /**
     * Define a PUT route.
     *
     * @param string $path The route path.
     * @param string|callable|array $callback The route handler.
     * @param callable[] $middleware Route specific middleware.
     */
    public function put(string $path, string|callable|array $callback, array $middleware = [])
    {

        $this->route($path, "PUT", $callback, $middleware);
    }



    /**
     * Define a DELETE route.
     *
     * @param string $path The route path.
     * @param string|callable|array $callback The route handler.
     * @param callable[] $middleware Route specific middleware.
     */
    public function delete(string $path, string|callable|array $callback, array $middleware = [])
    {
        $this->route($path, "DELETE", $callback, $middleware);
    }



    /**
     * Define a PATCH route.
     *
     * @param string $path The route path.
     * @param string|callable|array $callback The route handler.
     * @param callable[] $middleware Route specific middleware.
     */
    public function patch(string $path, string|callable|array $callback, array $middleware = [])
    {

        $this->route($path, "PATCH", $callback, $middleware);
    }


    /**
     * Define a OPTIONS route.
     *
     * @param string $path The route path.
     * @param string|callable|array $callback The route handler.
     * @param callable[] $middleware Route specific middleware.
     */
    public function options(string $path, string|callable|array $callback, array $middleware = [])
    {

        $this->route($path, "OPTIONS", $callback, $middleware);
    }

    /**
     * Define a HEAD route.
     *
     * @param string $path The route path.
     * @param string|callable|array $callback The route handler.
     * @param callable[] $middleware Route specific middleware.
     */
    public function head(string $path, string|callable|array $callback, array $middleware = [])
    {
        $this->route($path, "HEAD", $callback, $middleware);
    }


    /**
     * Register a global middleware.
     *
     * @param callable $globalMiddleware The middleware callback.
     */

    public function use(callable $globalMiddleware)
    {
        $this->globalMiddleware[] = $globalMiddleware;
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
        $path = $this->matchRoute($path);
        if (isset($this->route[$path][$method])) {

            $handler = $this->route[$path][$method]['action'];
            $middlewares = $this->route[$path][$method]['middleware'];

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
            $err = Coroutine::readFile(__DIR__ . "/Http/httperr/404.php");
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
    private function runStackMidleware($stack, $request, $response, $param = null)
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
                    $method = $middleware[1];
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
     * 
     * Enabling Websocket server
     * 
     * @return static
     * 
     */

    public function enableWebsocket()
    {

        $this->config['withWebsocket'] = true;
        return $this;
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
                $next();
                $method = $req->server['request_method'];
                $path = $req->server['request_uri'];
                $addres = $req->server['remote_addr'];
                $date = date("d/m/Y");
                $time = date("h:i");
                $status = $res->status;

                $text = "[$date $time] $addres:  $method $path.........[$status]\n";
                Coroutine::writeFile($this->config['logDir'], $text, FILE_APPEND);
                Console::log($text);
            } catch (\Throwable $th) {
                $method = $req->server['request_method'];
                $path = $req->server['request_uri'];
                $addres = $req->server['remote_addr'];
                $date = date("d/m/Y");
                $time = date("h:i");
                $text = "[$date $time] $addres: $method $path error " . $th->getMessage() . ":" . $th->getLine() . " in " . $th->getFile() . "\n";
                Coroutine::writeFile($this->config['logDir'], $text, FILE_APPEND);
                Console::error($text);
                $res->status(500);
                $res->response->end($th->getMessage());
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
        if (is_int($this->config['mode'] && !is_int($this->config['sock_type']))) {
            $this->server = new Server($this->host, $this->port, $this->config['mode'], $this->config['sock_type']);
        } else {
            if ($this->config['withWebsocket']) {
                $this->server = new WebSocketServer($this->host, $this->port);
            } else {

                $this->server = new Server($this->host, $this->port);
            }
        }
    }
    /**
     * Start the Swoole server.
     */
    public function start()
    {
        if (is_null($this->server)) {
            $this->init();
        }
        $this->server->set($this->serverSettings);

        Console::info("Server Started on {$this->protocol}://{$this->host}:{$this->port}");


        !$this->config['useOktaMiddleware'] ?: $this->use($this->OktaaMiddlewares()['log']);

        $this->server->on("request", function (SwooleRequest $request, Response $response) {
            $request = new Request($request);
            $response = new OktaResponse($response, $request, $this->config);
            $path = $request->request->server['request_uri'];
            $file = $this->config['publicDir'] . $path;

            if (is_file($file) && file_exists($file)) {
                $mime = mime_content_type($file);
                $response->header("Content-Type", $mime);
                $response->sendfile($file);
            } else {

                $this->AppHandler($request, $response);
            }
        });
        $this->server->start();
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

    /**
     * 
     * Websocket Handler
     * 
     * only available with config['withWebsocket']
     * 
     * @param callable $callback
     * 
     */


    public function onMessage(callable $callback)
    {
        if (!$this->config['withWebsocket']) {
            Console::error("You currently using http server, change app config to be websocket server");
            throw new Error("this method only available for websocket, please enable websocket first");
        }
        $this->init();
        Console::info("Websocket Started on ws://{$this->host}:{$this->port}");
        $this->server->on('message', $callback);
    }

    /**
     * 
     * Handle Server event
     * 
     * @param string $event
     * @param callable $callback
     */
    public function on($event, $callback)
    {

        $this->server->on($event, $callback);
    }

/**
 * 
 * filter url before calling action
 * 
 * @param string $route
 * @return string
 * 
 */
    private function matchRoute($route)
    {
        $route = rtrim($route);

        if (strlen($route) > 1) {
            if (str_ends_with($route, "/")) {
                $route =   substr($route, 0, -1);
            }
        }

        return $route;
    }
}
