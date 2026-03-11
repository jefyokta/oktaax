<?php

namespace Oktaax\Core;

use Oktaax\Http\Request;
use Oktaax\Http\Response;
use Oktaax\Http\Router;
use Oktaax\Interfaces\Injectable;

class Application
{
    private static $exceptionCatcher = [];
    private static $returnHandler = [];
    private static ?Request $request;
    private static ?Response $response;

    public Worker $worker;

    private static ?Application $instance = null;

    private static $booted = false;


    public static function create(Request $request, Response $response): static
    {
        self::$request = $request;
        self::$response = $response;
        self::$booted = true;

        return self::$instance = new static;
    }
    public static function getInstance(): Application
    {
        if (self::$instance === null) {
            self::$instance = new Application();
        }
        return self::$instance;
    }
    /**
     * Alias of create
     * @param Request $request
     * @param Response $response
     * @return Application
     */
    public static function setContext(Request $request, Response $response): Application
    {
        return self::create($request, $response);
    }

    public static function getRequest(): ?Request
    {
        return self::$request;
    }

    public static function getResponse(): ?Response
    {
        return self::$response;
    }

    /**
     * @template T of \Throwable
     *
     * @param class-string<T> $exception
     * @param \Closure(T): mixed $handler
     * @return static
     */
    public function catch(string $exception, \Closure $handler): static
    {
        static::$exceptionCatcher[$exception] = $handler;
        return $this;
    }
    /**
     * Inject Custom method to Request or Response class
     * @param class-string<Request | Response>$to 
     * @param class-string<\Oktaax\Contracts\Invokable> $invokable
     * @return Application
     */
    public function inject($to, $name, $invokable): static
    {
        /**
         * @var Injectable
         */

        if (!\in_array($to, [Request::class, Response::class])) {
            throw new \InvalidArgumentException("Cannot inject method to {$to}");
        }

        $to::inject($name, $invokable);

        return $this;
    }
    /**
     * @template T 
     *
     * @param class-string<T> $return
     * @param \Closure(T,Request,Response): mixed $handler
     * @return static
     */
    public function resolve(string $return, \Closure $handler): static
    {
        static::$returnHandler[$return] = $handler;
        return $this;
    }

    /**
     * alias of \Oktaax\Core\Application::resolve
     * @param mixed $return
     * @param \Closure $handler
     * @return Application
     */
    public function respond($return, \Closure $handler): static
    {

        return $this->resolve($return, $handler);
    }

    public function handle()
    {
        if (!self::$booted) {
            throw new \RuntimeException("Application didnt created yet!");
        }
        try {
            $response = Router::handle(self::$request);
            $this->dispatchReturn($response);
        } catch (\Throwable $th) {
            $this->dispatchException($th);
        } finally {
            self::$booted = false;
        }
    }

    private function dispatchReturn(mixed $result): void
    {
        if (!self::$response->response->isWritable()) {
            return;
        }

        $class = \is_object($result) ? $result::class : \gettype($result);

        if (isset(static::$returnHandler[$class])) {
            $handler = static::$returnHandler[$class];
            $handler($result, self::$request, self::$response);
            return;
        }

        if (\is_string($result)) {
            self::$response->end($result);
            return;
        }

        if (\is_array($result) || $result instanceof \Traversable) {
            self::$response->header("content-type", "application/json");
            self::$response->end(json_encode($result));
            return;
        }

        throw new \RuntimeException("Unhandled return type: $class");
    }

    private function dispatchException($th): void
    {

        $handler = $this->resolveException($th);

        if (null == $handler) {
            throw $th;
        }

        $handler($th);
    }

    private function resolveException(\Throwable $e): callable|null
    {
        foreach (static::$exceptionCatcher as $class => $handler) {
            if ($e instanceof $class) {
                return $handler;
            }
        }

        return null;
    }
};
