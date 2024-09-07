<?php

namespace Oktaax;

use Oktaax\Http\Response as OktaResponse;
use Swoole\Coroutine;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;

class Oktaa
{

    private $server;
    protected $route;
    protected $globalMiddleware = [];
    private $config = [
        "viewsDir" => "views/",
        "logDir" => "log"
    ];


    public function __construct(string $host, int $port)
    {

        $this->server = new Server($host, $port);

        fwrite(STDOUT, "\n");
        fwrite(STDOUT, "\033[44m\033[30m info \033[0m \033[95m Server Started on http://$host:$port \033[0m\n");
        fwrite(STDOUT, "\n");
        $this->use($this->OktaaMiddlewares()['log']);
        $this->server->on("request", function (Request $request, Response $response) {
            $response = new OktaResponse($response, $this->config['viewsDir']);
            $this->AppHandler($request, $response);
        });
    }
    public function setServer(array $setting)
    {

        $this->server->set($setting);
    }

    public function getServer(): Server
    {

        return $this->server;
    }
    public function set($key, $value)
    {
        $this->config[$key] = $value;
    }

    public function get(string $path, callable $callback, array $middleware = [])
    {
        $this->route['GET'][$path] = [
            "action" => $callback,
            "middleware" => $middleware
        ];
    }
    public function post(string $path, callable $callback, array $middleware = [])
    {
        $this->route['POST'][$path] = [
            "action" => $callback,
            "middleware" => $middleware
        ];
    }
    public function put(string $path, callable $callback, array $middleware = [])
    {
        $this->route['PUT'][$path] = [
            "action" => $callback,
            "middleware" => $middleware
        ];
    }
    public function delete(string $path, callable $callback, array $middleware = [])
    {
        $this->route['DELETE'][$path] = [
            "action" => $callback,
            "middleware" => $middleware
        ];
    }

    public function patch(string $path, callable $callback, array $middleware = [])
    {
        $this->route['PATCH'][$path] = [
            "action" => $callback,
            "middleware" => $middleware
        ];
    }

    public function options(string $path, callable $callback, array $middleware = [])
    {
        $this->route['OPTIONS'][$path] = [
            "action" => $callback,
            "middleware" => $middleware
        ];
    }

    public function head(string $path, callable $callback, array $middleware = [])
    {
        $this->route['HEAD'][$path] = [
            "action" => $callback,
            "middleware" => $middleware
        ];
    }

    public function use(callable $globalMiddleware)
    {
        $this->globalMiddleware[] = $globalMiddleware;
    }
    public function path() {}


    private function AppHandler(Request $request, OktaResponse $response)
    {


        $path = $request->server['request_uri'];
        $path = filter_var($path, FILTER_SANITIZE_URL);
        $method = $request->server['request_method'];

        $stack =  array_merge($this->globalMiddleware, [
            function ($request, $response, $next) use ($path, $method) {
                $this->proccesRequest($request, $response, $method, $path, $next);
            }
        ]);

        $this->runStackMidleware($stack, $request, $response);
    }
    private function proccesRequest(Request $request, OktaResponse $response, string $method, string $path, $next)
    {

        if (isset($this->route[$method][$path])) {
            $handler = $this->route[$method][$path]['action'];
            $middlewares = $this->route[$method][$path]['middleware'];

            $middlewaresStack = array_merge($middlewares, [
                function ($request, $response, $next, $param) use ($handler) {
                    $handler($request, $response, $param = null);
                }
            ]);
            $this->runStackMidleware($middlewaresStack, $request, $response);
        } else {
            $response->status(404);
            $err = Coroutine::readFile(__DIR__ . "/Http/httperr/404.php");
            $response->response->end($err);
        }
    }

    private function runStackMidleware($stack, $request, $response, $param = null)
    {


        $next = function ($param = null) use (&$stack, $request, $response, &$next) {
            if (!empty($stack)) {
                $middleware = array_shift($stack);
                if (is_callable($middleware)) {
                    $middleware($request, $response, $next, $param);
                }
            }
        };
        $next($param);
    }

    private function OktaaMiddlewares()
    {
        $log = function (Request $req, OktaResponse $res, $next) {

            try {
                $method = $req->server['request_method'];
                $path = $req->server['request_uri'];
                $addres = $req->server['remote_addr'];
                $date = date("d/m/Y");
                $time = date("h:i");
                $text = "[$date $time] $addres: $method $path.........\n";
                Coroutine::writeFile($this->config['logDir'], $text, FILE_APPEND);
                fwrite(STDOUT, "\n");
                fwrite(STDOUT, "\033[44m\033[30m info \033[0m \033[95m $text \033[0m\n");
                fwrite(STDOUT, "\n");
                $next();
            } catch (\Throwable $th) {
                $method = $req->server['request_method'];
                $path = $req->server['request_uri'];
                $addres = $req->server['remote_addr'];
                $date = date("d/m/Y");
                $time = date("h:i");
                $text = "[$date $time] $addres: $method $path error " . $th->getMessage() . "\n";
                Coroutine::writeFile($this->config['logDir'], $text, FILE_APPEND);
                fwrite(STDOUT, "\n");
                fwrite(STDOUT, "\033[41m\033[97m error \033[0m \033[93m $text \033[0m\n");
                fwrite(STDOUT, "\n");
                $res->status(500);
            }
        };


        return compact("log");
    }

    public function start()
    {
        $this->server->start();
    }
}
