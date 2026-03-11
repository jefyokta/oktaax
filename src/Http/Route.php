<?php

namespace Oktaax\Http;

use Oktaax\Contracts\Middleware;

class Route
{
    private $paramters = [];
    private $dynamic = false;
    public function __construct(private string $path, private $method, private $handler, private $middlewares = []) {}


    public function getPath()
    {
        return $this->path;
    }

    public function addMiddlewares($middlewares): Route
    {
        if (!\is_array($middlewares)) {
            $middlewares = [$middlewares];
        }
        $found =    array_find($middlewares, function ($middleware) {
            return !\is_subclass_of($middleware, Middleware::class);
        });
        if ($found !== null) {
            throw new \InvalidArgumentException("Middleware must be type of class-string " . Middleware::class . ", ", \gettype($found) . " given");
        }
        $this->middlewares = [$this->middlewares, $middlewares];
        return $this;
    }

    public function isMatch(string $url, string $method): bool
    {
        if (\is_array($this->method)) {
            if (!\in_array(strtolower($method), $this->method)) {
                return false;
            }
        } else {
            if (strtolower($method) !== strtolower($this->method)) {
                return false;
            }
        }

        if ($this->path === $url) {
            return true;
        }

        $paramNames = [];

        $pattern = preg_replace_callback('/\{([^}]+)\}/', function ($match) use (&$paramNames) {
            $paramNames[] = $match[1];
            return '([^/]+)';
        }, $this->path);

        $pattern = "#^" . $pattern . "$#";

        if (!preg_match($pattern, $url, $matches)) {
            return false;
        }

        array_shift($matches);

        $this->paramters = array_combine($paramNames, $matches);

        return true;
    }

    public function terminate(Request $request, Response $response)
    {
        $handlers = [...$this->middlewares, $this->handler];

        $next = function ($request, $response) {
            return null;
        };

        foreach (array_reverse($handlers) as $handler) {

            $next = function ($request, $response) use ($handler, $next) {

                if (\is_string($handler) && is_subclass_of($handler, Middleware::class)) {

                    $middleware = new $handler();

                    return $middleware->handle($request, $response, $next);
                }
                return $this->callHandler($handler, $request, $response);
            };
        }

        return $next($request, $response);
    }
    public function callHandler($handler, Request $request, Response $response)
    {

        return \call_user_func($handler, $request, $response, ...$this->paramters);
    }
}
