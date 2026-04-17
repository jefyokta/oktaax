<?php

/**
 * Oktaax - Real-time Websocket and HTTP Server using Swoole
 *
 * @package Oktaax
 * @author Jefyokta
 * @license MIT License
 */

namespace Oktaax;

use BadMethodCallException;
use Error;
use Oktaax\Core\Application;
use Oktaax\Core\Configuration;
use Oktaax\Core\Container;
use Oktaax\Core\Event\Finish;
use Oktaax\Core\Event\Request as EventRequest;
use Oktaax\Core\Event\Task;
use Oktaax\Core\Event\WorkerStart;
use Oktaax\Core\URL;
use Oktaax\Http\Router;
use Oktaax\Interfaces\View;
use Oktaax\Types\AppConfig;
use Oktaax\Utils\MethodProxy;
use Oktaax\Views\PhpView;
use Swoole\Http\Server as HttpServer;
use Swoole\WebSocket\Server as WebSocketServer;
use Symfony\Component\Translation\Exception\InvalidResourceException;

/**
 * A class to make a raw HTTP/WebSocket application server
 *
 * @package Oktaax
 *
 * @mixin \Oktaax\Http\Router
 *
 * @method void listen(int $port)
 * @method void listen(int $port, callable(string $url): void $callback)
 * @method void listen(int $port, string $host)
 * @method void listen(int $port, string $host, callable(string $url): void $callback)
 */
class Oktaax
{
    /**
     * Swoole server instance.
     *
     * @var HttpServer|WebSocketServer|null
     */
    protected HttpServer|WebSocketServer|null $server = null;

    /**
     * Router instance.
     *
     * @var Router
     */
    protected Router $router;


    protected $startCallback;

    /**
     * Registered custom events.
     *
     * @var array<string, callable>
     */
    protected array $customEvents = [];

    /**
     * Oktaax constructor.
     */
    public function __construct()
    {
        Configuration::set('app.host', '127.0.0.1');
        Configuration::set('app.port', 3000);
        Configuration::set('app.protocol', 'http');
        Configuration::set('app.debug', false);
        Configuration::set('app.name', 'Oktaax');
        Container::register(View::class, new PhpView("views/"));
        Container::register(AppConfig::class, new AppConfig(null, false, 300, 'Oktaax'));

        $this->router = new Router();
    }


    /**
     * Get server class 
     *
     * @return class-string<HttpServer|WebSocketServer>
     */
    protected function getServerClass(): string
    {
        return HttpServer::class;
    }

    /**
     * Initialize Swoole server.
     *
     * @return void
     */
    protected function init(): void
    {
        $host = Configuration::get('app.host');
        $port = Configuration::get('app.port');

        $mode = Configuration::get('server.mode');
        $sock = Configuration::get('server.sock_type');

        $class = $this->getServerClass();

        $this->server = ($mode && $sock)
            ? new $class($host, $port, $mode, $sock)
            : new $class($host, $port);
        Container::register(HttpServer::class, $this->server);
    }


    /**
     * Set view engine.
     *
     * @param View $view
     * @return static
     */
    public function setView(View $view)
    {
        Container::register(View::class, $view);
        return $this;
    }

    /**
     * Set Swoole server configuration.
     *
     * @param array|string $key
     * @param mixed $value
     * @return static
     */
    public function setServer(array|string $key, mixed $value = null)
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                Configuration::set("server.$k", $v);
            }
        } else {
            Configuration::set("server.$key", $value);
        }

        return $this;
    }

    /**
     * Enable SSL.
     *
     * @param string $cert
     * @param string $key
     * @return static
     */
    public function withSSL(string $cert, string $key)
    {
        if (!file_exists($cert)) {
            throw new InvalidResourceException("Certificate not found!");
        }

        if (!file_exists($key)) {
            throw new InvalidResourceException("Key not found!");
        }

        $this->setServer([
            'ssl_cert_file' => $cert,
            'ssl_key_file'  => $key,
            'sock_type'     => SWOOLE_SOCK_TCP | SWOOLE_SSL,
            'mode'          => SWOOLE_BASE,
        ]);

        Configuration::set('app.protocol', 'https');

        return $this;
    }

    /**
     * Enable CSRF protection.
     *
     * @param string $appkey
     * @param int $expr
     * @return void
     */
    public function useCsrf(string $appkey, int $expr = 300)
    {
        Configuration::set('app.key', $appkey);
        Configuration::set('app.csrf_exp', $expr);
        Configuration::set('app.csrf', true);
    }


    /**
     * Start server.
     *
     * @param int $port
     * @param string|callable|null $hostOrCallback
     * @param callable|null $callback
     * @return void
     */
    public function listen(
        int $port,
        string|callable|null $hostOrCallback = null,
        ?callable $callback = null
    ): void {

        Configuration::set('app.port', $port);
        Configuration::set(
            'app.host',
            is_string($hostOrCallback) ? $hostOrCallback : '127.0.0.1'
        );


        $this->init();
        $this->callIfExists('boot');

        $this->callIfExists('eventRegistery');
        $this->startCallback = is_callable($hostOrCallback) ? $hostOrCallback : $callback;
        $this->registerCoreEvents();
        $this->registerCustomEvents();
        $this->server->set(Configuration::get('server', []));

        $this->server->start();
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
     * Register custom event.
     *
     * @param string $event
     * @param callable $handler
     * @return void
     */
    public function on(string $event, callable $handler): void
    {
        $event = strtolower($event);

        $handled = array_map('strtolower', $this->getHandledEvents());

        if (\in_array($event, $handled)) {
            throw new Error("Cannot declare handled event: {$event}");
        }

        $this->customEvents[$event] = $handler;
    }

    /**
     * Register core events.
     * @return void
     */
    protected function registerCoreEvents(): void
    {

        $this->onWorkerStart();
        $this->onRequest();


        if ($this->taskEnabled()) {
            $this->server->on("task", fn(...$args) => (new Task())->handle(...$args));
            $this->server->on("finish", fn(...$args) => (new Finish())->handle(...$args));
        }
    }

    protected function onWorkerStart()
    {
        $this->server->on("workerstart", function ($server, $workerId) {

            Application::setServer($server);

            $host = Configuration::get('app.host');
            $port = Configuration::get('app.port');
            (new WorkerStart(
                new URL(
                    $host,
                    $port,
                    Configuration::get('app.protocol', 'http'),
                    method_exists($this, 'ws')
                ),
                $this->startCallback
            ))->handle($server, $workerId);
        });
    }
    protected function onRequest()
    {
        $this->server->on("request", function ($request, $response) {
            (new EventRequest())->handle($request, $response);
        });
    }

    /**
     * Register user events.
     *
     * @return void
     */
    protected function registerCustomEvents(): void
    {
        foreach ($this->customEvents as $event => $handler) {
            $this->server->on($event, $handler);
        }
    }

    /**
     * Handled events .
     *
     * @return array<int, string>
     */
    public function getHandledEvents(): array
    {
        return ['request', 'workerstart'];
    }


    protected function callIfExists(string $method): void
    {
        if (method_exists($this, $method)) {
            $this->{$method}();
        }
    }

    protected function taskEnabled(): bool
    {
        return Configuration::get('server.task_worker_num', 0) > 0;
    }

    /**
     * Reload server.
     *
     * @return void
     */
    public function reload(): void
    {
        $this->server->reload();
    }


    /**
     * Proxy router methods.
     *
     * @param string $name
     * @param array $arguments
     * @return static
     */
    public function __call($name, $arguments)
    {
        if (method_exists($this->router, $name)) {
            $this->router->{$name}(...$arguments);
            return $this;
        }

        throw new BadMethodCallException("Method {$name} does not exist");
    }
}
