<?php

namespace Oktaax;

use Closure;
use LogicException;
use Oktaax\Application\ApplicationContext;
use Oktaax\Application\Bootstrap;
use Oktaax\Application\Context;
use Oktaax\Application\RequestContext;
use Oktaax\Application\ServerAddress;
use Oktaax\Application\TableRegistry;
use Oktaax\Application\WorkerContext;
use Oktaax\Application\WorkerLifecycle;
use Oktaax\Config\Configuration;
use Oktaax\Container\Container;
use Oktaax\Contracts\View;
use Oktaax\Http\HttpKernel;
use Oktaax\Http\Request;
use Oktaax\Http\Response;
use Oktaax\Http\Routing\Router;
use Oktaax\Runtime\ServerRuntime;
use Oktaax\Task\TaskDispatcher;
use Oktaax\Websocket\Endpoint;
use Oktaax\Websocket\WebSocketKernel;

/** Owns application definitions; worker and request resources have separate scopes. */
class Application
{
    public const string VERSION = '0.0.1';
    public const SWOOLE_VERSION_ID = SWOOLE_VERSION_ID;

    protected Router $router;
    private Configuration $configuration;
    private Container $services;
    private Context $requestContext;
    private HttpKernel $httpKernel;
    private WorkerLifecycle $workers;
    private TableRegistry $tables;
    private TaskDispatcher $tasks;
    private WebSocketKernel $websockets;
    private ?ServerRuntime $runtime = null;
    private ?object $nativeServer = null;
    private string $state = 'configuring';
    /**
     * Observers retained for this application lifetime.
     *
     * @var list<\Closure(\Oktaax\Http\Request, \Oktaax\Http\Response): mixed>
     */
    private array $afterRequest = [];
    /**
     * Observers retained for this application lifetime.
     *
     * @var list<\Closure(\Oktaax\Http\Request, \Oktaax\Http\Response, \Oktaax\Event\Event): mixed>
     */
    private array $responseClosed = [];
    /**
     * Observers retained for this application lifetime.
     *
     * @var list<\Closure(\Oktaax\Application\WorkerContext, \Oktaax\Application): mixed>
     */
    private array $workerStart = [];
    /**
     * Observers retained for this application lifetime.
     *
     * @var list<\Closure(\Oktaax\Application\WorkerContext, \Oktaax\Application): mixed>
     */
    private array $workerStop = [];
    /**
     * Observers retained for this application lifetime.
     *
     * @var list<\Closure(int, int, int, int, \Oktaax\Application): mixed>
     */
    private array $workerError = [];
    /**
     * Observers retained for this application lifetime.
     *
     * @var list<\Closure(\Oktaax\Application): mixed>
     */
    private array $shutdown = [];
    /**
     * Application-owned helper callables copied onto each HTTP request or response.
     *
     * @var array<string, callable>
     */
    private array $requestHelpers = [];
    /**
     * Application-owned helper callables copied onto each HTTP request or response.
     *
     * @var array<string, callable>
     */
    private array $responseHelpers = [];
    public ?WorkerContext $worker = null;

    /**
     * Create an isolated application and register default services and exception policy.
     *
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->configuration = new Configuration(
            array_replace_recursive(Bootstrap::defaults(), $config),
        );
        $this->services = new Container();
        $this->router = new Router();
        $this->requestContext = new Context();
        $this->tables = new TableRegistry();
        $this->httpKernel = new HttpKernel($this);
        $this->tasks = new TaskDispatcher($this);
        $this->workers = new WorkerLifecycle($this);
        $this->websockets = new WebSocketKernel($this);
        Bootstrap::register($this);
    }

    /**
     * Read a dot-separated value, or return the configuration object when no key is supplied.
     *
     * @return ($key is null ? \Oktaax\Config\Configuration : mixed)
     */
    public function config(?string $key = null, mixed $default = null): mixed
    {
        return $key === null
            ? $this->configuration
            : $this->configuration->get($key, $default);
    }

    /**
     * Resolve a registered service, or return the container when no service ID is supplied.
     *
     * @return ($service is null ? Container : object)
     * @throws \OutOfBoundsException When the requested service is not registered.
     * @throws LogicException When service resolution violates its lifetime.
     */
    public function container(?string $service = null): mixed
    {
        return $service === null
            ? $this->services
            : $this->services->get($service);
    }

    public function router(): Router
    {
        return $this->router;
    }
    public function context(): Context
    {
        return $this->requestContext;
    }
    public function kernel(): HttpKernel
    {
        return $this->httpKernel;
    }
    public function tables(): TableRegistry
    {
        return $this->tables;
    }
    public function tasks(): TaskDispatcher
    {
        return $this->tasks;
    }
    public function workers(): WorkerLifecycle
    {
        return $this->workers;
    }
    /**
     * Register or retrieve a WebSocket endpoint by exact path before application boot.
     *
     * @throws \InvalidArgumentException When the endpoint path is invalid.
     * @throws \LogicException When application definitions are frozen.
     */
    public function websocket(string $path): Endpoint
    {
        return $this->websockets->endpoint($path);
    }

    /**
     * Register or retrieve an endpoint through the websocket() shorthand.
     *
     * @return \Oktaax\Websocket\Endpoint
     * @see self::websocket()
     */
    public function ws(string $path)
    {
        return $this->websocket($path);
    }
    public function websockets(): WebSocketKernel
    {
        return $this->websockets;
    }
    public function state(): string
    {
        return $this->state;
    }
    public function nativeServer(): ?object
    {
        return $this->nativeServer;
    }
    public function runtime(): ?ServerRuntime
    {
        return $this->runtime;
    }
    public function getRequest(): ?Request
    {
        return $this->requestContext->request();
    }
    public function getResponse(): ?Response
    {
        return $this->requestContext->response();
    }

    /**
     * Run in this application scope and restore the previous scope even when the callback throws.
     *
     * @param callable(): TResult $callback
     * @return TResult
     * @template TResult
     */
    public function within(callable $callback): mixed
    {
        return ApplicationContext::run($this, $callback);
    }

    public function assertConfigurable(): void
    {
        if ($this->state !== 'configuring') {
            throw new LogicException(
                'Application definitions are frozen after boot.',
            );
        }
    }

    public function boot(): void
    {
        if ($this->state !== 'configuring') {
            return;
        }
        Bootstrap::boot($this);
        $this->state = 'booted';
    }

    /**
     * Validate capabilities, freeze definitions, and run this application once with guaranteed teardown.
     *
     * @throws \LogicException When this application has already run or the runtime lacks required capabilities.
     * @throws \Throwable When startup, execution, or shutdown fails.
     */
    public function run(ServerRuntime $runtime): void
    {
        if (!in_array($this->state, ['configuring', 'booted'], true)) {
            throw new LogicException('An application can only run once.');
        }
        $runtime->validate($this);
        if (
            $this->websockets->hasEndpoints() &&
            !$runtime->supportsWebSockets()
        ) {
            throw new LogicException(
                'The active runtime does not support WebSocket endpoints. Use ProcessRuntime.',
            );
        }
        $this->boot();
        $this->runtime = $runtime;
        $this->state = 'running';
        $failure = null;
        try {
            $this->within(fn() => $runtime->start($this));
        } catch (\Throwable $error) {
            $failure = $error;
        }
        try {
            $this->shutdown();
        } catch (\Throwable $cleanup) {
            if ($failure === null) {
                $failure = $cleanup;
            } else {
                error_log('Application shutdown also failed: ' . $cleanup);
            }
        }
        if ($failure !== null) {
            throw $failure;
        }
    }

    public function stop(): void
    {
        if ($this->state === 'running') {
            $this->state = 'stopping';
            $this->runtime?->stop();
        }
    }

    public function shutdown(): void
    {
        if ($this->state === 'stopped') {
            return;
        }
        $this->state = 'stopping';
        try {
            $this->within(
                fn() => Exception\CleanupException::run([
                    fn() => $this->workers->stop(),
                    fn() => $this->services->close(),
                    ...array_map(
                        fn($cb) => fn() => $cb($this),
                        $this->shutdown,
                    ),
                ]),
            );
        } finally {
            $this->state = 'stopped';
        }
    }

    /** Called only by the runtime; no service definitions are modified. */
    public function attachServer(object $server): void
    {
        $this->nativeServer = $server;
        $this->services->instance($server::class, $server);
        if ($server instanceof \Swoole\Http\Server) {
            $this->services->instance(\Swoole\Http\Server::class, $server);
        }
    }

    public function address(): ServerAddress
    {
        return new ServerAddress(
            $this->config('app.host'),
            $this->config('app.port'),
            $this->config('app.protocol'),
            $this->nativeServer instanceof \Swoole\WebSocket\Server,
        );
    }

    private function attachRequest(
        Request $request,
        Response $response,
    ): RequestContext {
        if ($this->getRequest() !== null) {
            throw new LogicException(
                'A request is already active in this application context.',
            );
        }
        $context = new RequestContext($this, $request, $response);
        $this->requestContext->set(Request::class, $request);
        $this->requestContext->set(Response::class, $response);
        $this->requestContext->set(RequestContext::class, $context);
        return $context;
    }

    /**
     * Execute the HTTP kernel inside this application scope; an active coroutine is required.
     *
     * @throws \LogicException When another request is active in this coroutine for this application.
     * @throws \Throwable When exception handling or request cleanup fails.
     */
    public function handleRequest(Request $request, Response $response): void
    {
        $this->within(function () use ($request, $response) {
            $context = $this->attachRequest($request, $response);
            $this->httpKernel->handle($context);
        });
    }

    /**
     * Register an exception response handler during application or worker bootstrap.
     *
     * @template TException of \Throwable
     * @param class-string<TException> $type
     * @param \Closure(TException, \Oktaax\Http\Request|null, \Oktaax\Http\Response|null): mixed $handler
     * @throws \LogicException When called during an HTTP request.
     */
    public function catch(string $type, Closure $handler): static
    {
        if ($this->getRequest() !== null) {
            throw new LogicException(
                'Register exception handlers during application or worker bootstrap.',
            );
        }
        $this->httpKernel->exceptions()->register($type, $handler);
        return $this;
    }

    /**
     * Register an emitter for a returned value type; the callback sends the response itself.
     *
     * @param \Closure(mixed, \Oktaax\Http\Request, \Oktaax\Http\Response): mixed $handler
     */
    public function respond(string $type, Closure $handler): static
    {
        if ($this->getRequest() !== null) {
            throw new LogicException(
                'Register response handlers during application or worker bootstrap.',
            );
        }
        $this->httpKernel->emitter()->register($type, $handler);
        return $this;
    }

    /**
     * Observe completed HTTP handler execution; a detached stream may still be open.
     *
     * @param \Closure(\Oktaax\Http\Request, \Oktaax\Http\Response): mixed $callback
     * @throws \LogicException When application definitions are frozen.
     */
    public function afterRequest(Closure $callback): static
    {
        $this->assertConfigurable();
        $this->afterRequest[] = $callback;
        return $this;
    }

    /**
     * Observe HTTP response completion or abort, independently of request cleanup.
     *
     * @param \Closure(\Oktaax\Http\Request, \Oktaax\Http\Response, \Oktaax\Event\Event): mixed $callback
     * @throws \LogicException When application definitions are frozen.
     */
    public function onResponseClosed(Closure $callback): static
    {
        $this->assertConfigurable();
        $this->responseClosed[] = $callback;
        return $this;
    }

    /**
     * Register a worker-start observer before boot; worker-scoped services are available to it.
     *
     * @param \Closure(\Oktaax\Application\WorkerContext, \Oktaax\Application): mixed $callback
     * @throws \LogicException When application definitions are frozen.
     */
    public function onWorkerStart(Closure $callback): static
    {
        $this->assertConfigurable();
        $this->workerStart[] = $callback;
        return $this;
    }

    /**
     * Observe worker stop before worker-scoped services are disposed.
     *
     * @param \Closure(\Oktaax\Application\WorkerContext, \Oktaax\Application): mixed $callback
     * @throws \LogicException When application definitions are frozen.
     */
    public function onWorkerStop(Closure $callback): static
    {
        $this->assertConfigurable();
        $this->workerStop[] = $callback;
        return $this;
    }

    /**
     * Observe native worker failure with worker ID, PID, exit code, signal, and application.
     *
     * @param \Closure(int, int, int, int, \Oktaax\Application): mixed $callback
     * @throws \LogicException When application definitions are frozen.
     */
    public function onWorkerError(Closure $callback): static
    {
        $this->assertConfigurable();
        $this->workerError[] = $callback;
        return $this;
    }

    public function reportWorkerError(
        int $id,
        int $pid,
        int $exitCode,
        int $signal,
    ): void {
        foreach ($this->workerError as $callback) {
            $callback($id, $pid, $exitCode, $signal, $this);
        }
    }

    /**
     * Register an application teardown observer before boot.
     *
     * @param \Closure(\Oktaax\Application): mixed $callback
     * @throws \LogicException When application definitions are frozen.
     */
    public function onShutdown(Closure $callback): static
    {
        $this->assertConfigurable();
        $this->shutdown[] = $callback;
        return $this;
    }

    /**
     * Return registered lifecycle callbacks in registration order.
     *
     * @return list<\Closure(\Oktaax\Http\Request, \Oktaax\Http\Response): mixed>
     * @internal Used by the kernel and worker runtime.
     */
    public function afterRequestCallbacks(): array
    {
        return $this->afterRequest;
    }
    /**
     * Return registered lifecycle callbacks in registration order.
     *
     * @return list<\Closure(\Oktaax\Http\Request, \Oktaax\Http\Response, \Oktaax\Event\Event): mixed>
     * @internal Used by the kernel and worker runtime.
     */
    public function responseClosedCallbacks(): array
    {
        return $this->responseClosed;
    }
    /**
     * Return registered lifecycle callbacks in registration order.
     *
     * @return list<\Closure(\Oktaax\Application\WorkerContext, \Oktaax\Application): mixed>
     * @internal Used by the kernel and worker runtime.
     */
    public function workerStartCallbacks(): array
    {
        return $this->workerStart;
    }
    /**
     * Return registered lifecycle callbacks in registration order.
     *
     * @return list<\Closure(\Oktaax\Application\WorkerContext, \Oktaax\Application): mixed>
     * @internal Used by the kernel and worker runtime.
     */
    public function workerStopCallbacks(): array
    {
        return $this->workerStop;
    }

    /**
     * Register a request or response helper before boot; supplied helper arguments are forwarded unchanged.
     *
     * @param class-string<\Oktaax\Http\Request>|class-string<\Oktaax\Http\Response> $to
     * @param class-string<callable-object>|callable $helper
     * @throws \InvalidArgumentException When the target is not Request or Response.
     * @throws \LogicException When application definitions are frozen.
     */
    public function inject(
        string $to,
        string $name,
        string|callable $helper,
    ): static {
        $this->assertConfigurable();
        $helper = is_callable($helper) ? $helper : new $helper();
        if ($to === Request::class) {
            $this->requestHelpers[$name] = $helper;
        } elseif ($to === Response::class) {
            $this->responseHelpers[$name] = $helper;
        } else {
            throw new \InvalidArgumentException(
                'Helpers can only be added to Request or Response.',
            );
        }
        return $this;
    }

    /**
     * Return helper callables to bind to a request or response.
     *
     * @return array<string, callable>
     * @internal
     */
    public function requestHelpers(): array
    {
        return $this->requestHelpers;
    }
    /**
     * Return helper callables to bind to a request or response.
     *
     * @return array<string, callable>
     * @internal
     */
    public function responseHelpers(): array
    {
        return $this->responseHelpers;
    }

    public function setView(View $view): static
    {
        $this->services->register(View::class, $view);
        return $this;
    }

    public function get(
        string $path,
        string|callable|array $handler,
        mixed ...$middleware,
    ): static {
        $this->router->get($path, $handler, ...$middleware);
        return $this;
    }

    public function post(
        string $path,
        string|callable|array $handler,
        mixed ...$middleware,
    ): static {
        $this->router->post($path, $handler, ...$middleware);
        return $this;
    }

    public function put(
        string $path,
        string|callable|array $handler,
        mixed ...$middleware,
    ): static {
        $this->router->put($path, $handler, ...$middleware);
        return $this;
    }

    public function patch(
        string $path,
        string|callable|array $handler,
        mixed ...$middleware,
    ): static {
        $this->router->patch($path, $handler, ...$middleware);
        return $this;
    }

    public function delete(
        string $path,
        string|callable|array $handler,
        mixed ...$middleware,
    ): static {
        $this->router->delete($path, $handler, ...$middleware);
        return $this;
    }

    public function head(
        string $path,
        string|callable|array $handler,
        mixed ...$middleware,
    ): static {
        $this->router->head($path, $handler, ...$middleware);
        return $this;
    }

    public function options(
        string $path,
        string|callable|array $handler,
        mixed ...$middleware,
    ): static {
        $this->router->options($path, $handler, ...$middleware);
        return $this;
    }

    public function use(mixed ...$middleware): static
    {
        $this->router->use(...$middleware);
        return $this;
    }

    /**
     * Register scoped middleware without adding a route prefix.
     *
     * @param callable(\Oktaax\Http\Routing\Router): mixed $callback
     */
    public function middleware(
        callable|array|\Oktaax\Contracts\Middleware $middleware,
        callable $callback,
    ): static {
        $this->router->middleware($middleware, $callback);
        return $this;
    }
}
