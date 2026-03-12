<?php

namespace Oktaax\Core;

use Oktaax\Console;
use Oktaax\Core\Dispatcher\ExceptionDispatcher;
use Oktaax\Core\Dispatcher\ReturnDispatcher;
use Oktaax\Http\Request;
use Oktaax\Http\Response;
use Oktaax\Http\Router;


class Application
{

    private Context $context;

    public Worker $worker;

    private static ?Application $instance = null;

    private static $booted = false;

    private ReturnDispatcher $return;

    private ExceptionDispatcher $exception;
    private function __construct()
    {
        $this->return = new ReturnDispatcher();
        $this->exception = new ExceptionDispatcher();
        $this->context = new Context();
    }

    public static function create(Request $request, Response $response): static
    {
        $app = self::getInstance();

        $app->context->set("request", $request);
        $app->context->set("response", $response);

        self::$booted = true;

        return $app;
    }
    public static function getInstance(): Application
    {
        if (self::$instance === null) {
            self::$instance = new static;
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
    public function context(): Context
    {
        return $this->context;
    }

    /**
     * Summary of getRequest
     * @return  ?Request
     */
    public static function getRequest(): ?Request
    {
        return self::getInstance()
            ->context
            ->get("request");
    }

    /**
     * Summary of getResponse
     * 
     * @return ?Response
     */
    public static function getResponse(): ?Response
    {
        return self::getInstance()
            ->context->get('response') ?? null;
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
        $this->exception->register($exception, $handler);
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
        $this->return->register($return, $handler);
        return $this;
    }

    /**
     * alias of \Oktaax\Core\Application::resolve
     * @template T
     * @param class-string<T> $return
     * @param \Closure(T,Request,Response): mixed $handler
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
            $response = Router::handle($request = self::getRequest());
            $this->return->dispatch(
                $response,
                $request,
                self::getResponse()
            );
        } catch (\Throwable $th) {
            $this->exception->dispatch($th);
        } finally {
            $this->context->destroy();
            self::$booted = false;
        }
    }
};
