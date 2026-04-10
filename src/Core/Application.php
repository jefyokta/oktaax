<?php

namespace Oktaax\Core;

use Oktaax\Core\Dispatcher\ExceptionDispatcher;
use Oktaax\Core\Dispatcher\ReturnDispatcher;
use Oktaax\Http\Request;
use Oktaax\Http\Response;
use Oktaax\Http\Router;
use Oktaax\Utils\MethodProxy;
use Swoole\Http\Server;
use Swoole\WebSocket\Server as WebSocketServer;

/**
 * Main Oktaax Application Kernel
 *
 * Handles request lifecycle, exception dispatching,
 * return value resolving, and coroutine context management.
 */
class Application
{
    /**
     * Coroutine context container
     */
    private Context $context;

    /**
     * Worker instance (assigned during server bootstrap)
     */
    public Worker $worker;

    /**
     * Singleton instance
     */
    private static ?Application $instance = null;

    /**
     * Indicates application request lifecycle is active
     */
    private static bool $booted = false;

    /**
     * Return value dispatcher
     */
    private ReturnDispatcher $return;

    /**
     * Exception dispatcher
     */
    private ExceptionDispatcher $exception;

    private static Server | WebSocketServer $server;

    /**
     * @var array<int,\Closure(Request,Response):void>
     */
    private array $finallyCallbacks = [];

    private function __construct()
    {
        $this->return = new ReturnDispatcher();
        $this->exception = new ExceptionDispatcher();
        $this->context = new Context();
    }

    /**
     * Create application request context
     *
     * @param Request $request
     * @param Response $response
     * @return static
     */
    public static function create(Request $request, Response $response): static
    {
        $app = self::getInstance();

        $app->context->set(Request::class, $request);
        $app->context->set(Response::class, $response);

        self::$booted = true;

        return $app;
    }
    public  static function setServer(Server | WebSocketServer $server)
    {
        self::$server = $server;
    }
    public static function server()
    {

        return self::warm(Server::class);
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(): Application
    {
        if (self::$instance === null) {
            self::$instance = new static();
        }
        return self::$instance;
    }

    /**
     * Alias of create()
     */
    public static function setContext(Request $request, Response $response): Application
    {
        return self::create($request, $response);
    }

    /**
     * Get coroutine context container
     */
    public static function context(): Context
    {
        return self::getInstance()->context;
    }

    /**
     * Get current request from coroutine context
     */
    public static function getRequest(): ?Request
    {
        return self::getInstance()
            ->context
            ->get(Request::class);
    }

    /**
     * Get current response from coroutine context
     */
    public static function getResponse(): ?Response
    {
        return self::getInstance()
            ->context
            ->get(Response::class);
    }

    /**
     * Register exception handler
     *
     * @template T of \Throwable
     * @param class-string<T> $exception
     * @param \Closure(T):mixed $handler
     * @return static
     */
    public function catch(string $exception, \Closure $handler): static
    {
        $this->exception->register($exception, $handler);

        return $this;
    }

    /**
     * Inject custom method to Request or Response
     *
     * @param class-string<Request|Response> $to
     * @param string $name
     * @param class-string<\Oktaax\Contracts\Invokable> $invokable
     */
    public function inject(string $to, string $name, string $invokable): static
    {
        if (!\in_array($to, [Request::class, Response::class], true)) {
            throw new \InvalidArgumentException("Cannot inject method to {$to}");
        }

        $to::inject($name, $invokable);

        return $this;
    }

    /**
     * Register return value resolver
     *
     * @template T
     * @param class-string<T> $return
     * @param \Closure(T,Request,Response):mixed $handler
     */
    public function resolve(string $return, \Closure $handler): static
    {
        $this->return->register($return, $handler);

        return $this;
    }
    /**
     * @template T
     * @param class-string<T> $class
     * @return T
     */
    public static function warm($class)
    {
        return Container::get($class);
    }

    public  function config()
    {

        return new MethodProxy(Configuration::class);
    }
    public function container()
    {
        return new MethodProxy(Container::class);
    }

    /**
     * Alias of resolve()
     *
     * @template T
     * @param class-string<T> $return
     * @param \Closure(T,Request,Response):mixed $handler
     */
    public function respond(string $return, \Closure $handler): static
    {
        return $this->resolve($return, $handler);
    }

    /**
     * Handle incoming HTTP request lifecycle
     */
    public function handle(): void
    {
        if (!self::$booted) {
            throw new \RuntimeException("Application context has not been created.");
        }

        try {

            $request = self::getRequest();

            $result = Router::handle($request);

            $this->return->dispatch(
                $result,
                $request,
                self::getResponse()
            );
        } catch (\Throwable $th) {

            $this->exception->dispatch($th);
        } finally {

            $req = self::getRequest();
            $res = self::getResponse();

            foreach ($this->finallyCallbacks as $callback) {
                $callback($req, $res);
            }

            $this->context->destroy();

            self::$booted = false;
        }
    }

    /**
     * Register finally lifecycle callback
     *
     * Executed after request lifecycle finishes.
     *
     * @param \Closure(Request,Response):void $callback
     */
    public function finally(\Closure $callback): static
    {
        $this->finallyCallbacks[] = $callback;

        return $this;
    }

    /**
     * Alias of finally()
     */
    public function after(\Closure $callback): static
    {
        return $this->finally($callback);
    }
}
