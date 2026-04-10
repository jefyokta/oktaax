<?php

namespace Oktaax\Http;

use Oktaax\Contracts\Middleware;
use Oktaax\Core\Application;
use Oktaax\Core\Promise\Promise;
use Oktaax\Utils\AsyncTransform;
use Oktaax\Utils\Invoker;

/**
 * HTTP Route definition
 *
 * Route objects are immutable and stateless so they are safe
 * for concurrent coroutine execution in Swoole.
 */
class Route
{
    /**
     * Whether the route contains dynamic parameters
     */
    private bool $dynamic = false;

    /**
     * Compiled regex pattern
     */
    private string $pattern;

    /**
     * Parameter names extracted from path
     *
     * @var array<int,string>
     */
    private array $paramNames = [];

    /**
     * Callable invoker
     */
    private Invoker $invoker;

    /**
     * @param string $path
     * @param string $method
     * @param callable $handler
     * @param array<int,string|callable> $middlewares
     */
    public function __construct(
        private string $path,
        private string $method,
        private $handler,
        //todo #1
        //ini middleware nya isinya globalmidd, baru dinamic middleware, jadi untuk global bakal banyak duplikatnya sih tiap bikin objet route ngisi stack pake hal yang sama, solusinya bisa disimpan stack sendiri si global midnya nanti merge aja, tapi gabisa dinamyc misal mau except glob mid a untuk router x
        private array $middlewares = []
    ) {

        if (AsyncTransform::isHasAsyncAttribute($this->handler)) {
            //todo #2
            //wrap handler dengan promise
        }
         //todo #3
        //iterasi middleware disni cek juga kalo dia async juga
        $this->compile();
        $this->invoker = new Invoker();
    }

    /**
     * Compile route path into regex pattern
     */
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

    /**
     * Determine whether route is dynamic
     */
    public function isDynamic(): bool
    {
        return $this->dynamic;
    }

    /**
     * Match incoming request
     *
     * @param string $url
     * @param string $method
     * @return array<string,string>|false
     */
    public function match(string $url, string $method = ''): array|false
    {
        if (!$this->dynamic) {

            if ($this->path === $url && $this->method === strtoupper($method)) {
                return [];
            }

            return false;
        }

        if (!preg_match($this->pattern, $url, $matches)) {
            return false;
        }

        array_shift($matches);

        return array_combine($this->paramNames, $matches);
    }

    /**
     * Execute route handler and middleware stack
     *
     * @param Request $request
     * @param Response $response
     * @param array<string,string> $params
     */
    public function terminate(
        Request $request,
        Response $response,
        array $params = []
    ) {

        $stack = [...$this->middlewares, $this->handler];

        $request->params = $params;

        $next = function () use (&$stack, $request, $response, &$next) {

            if (empty($stack)) {
                return null;
            }

            $cb = array_shift($stack);

            return $this->callHandler($cb, $request, $response, $next);
        };

        return $next();
    }

    /**
     * Call middleware or handler
     *
     * @param callable|string $handler
     */
    private function callHandler(
        $handler,
        Request $request,
        Response $response,
        callable $next
    ) {

        if (is_subclass_of($handler, Middleware::class)) {

            if (is_string($handler)) {
                $handler = new $handler();
            }

            return $handler->handle($request, $response, $next);
        }

        return $this->invoker
            ->addContext($request)
            ->addContext($response)
            ->addContext(Application::context())
            ->setPositional([$request, $response, $request->params])
            ->call($handler);
    }

    /**
     * Get route path
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get HTTP method
     */
    public function getMethod(): string
    {
        return $this->method;
    }
}
