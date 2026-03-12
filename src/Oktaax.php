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

use BadMethodCallException;
use Error;
use Oktaax\Core\Application;
use Oktaax\Core\Event\Request as EventRequest;
use Oktaax\Core\Event\WorkerStart;
use Oktaax\Core\URL;

use Oktaax\Http\Router;
use Oktaax\Interfaces\View;
use Oktaax\Types\AppConfig;
use Oktaax\Types\OktaaxConfig;
use Oktaax\Views\PhpView;
use Swoole\Http\Server as HttpServer;
use Swoole\WebSocket\Server as WebSocketServer;
use Symfony\Component\Translation\Exception\InvalidResourceException;

/**
 * 
 * A class to make a raw HTTP application server
 * 
 * @package Oktaax
 * 
 * 
 */
/**
 * @mixin Router
 * @method bool listen(int $port)
 * @method bool listen(int $port, callable(string $url) $callback)
 * @method bool listen(int $port, string $host)
 * @method bool listen(int $port, string $host, callable(string $url) $callback)
 * @method Oktaax setServer(string $key, mixed $value);
 * @method Oktaax setServer(array $settings);
 */
class Oktaax
{
    /**
     * Swoole HTTP server instance.
     *
     * @var \Swoole\Http\Server|\Swoole\WebSocket\Server|null
     */
    protected HttpServer|WebSocketServer|null $server = null;

    private Application $app;

    /**
     * Server Settings
     * 
     * @var array
     */
    protected array $serverSettings = [];

    protected $swoolevents = [];


    /**
     * Configuration settings for the application.
     *
     * @var OktaaxConfig
     */

    protected OktaaxConfig  $config;

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
    protected Router $router;

    protected string $protocol = 'http';

    /**
     * 
     * Middleware path
     * @var array
     * 
     */
    private array $pathMiddlewares = [];

    public function __construct()
    {
        $this->pathMiddlewares = [];

        $this->config = new OktaaxConfig(
            new PhpView("views/"),
            'log',
            false,
            null,
            null,
            new AppConfig(null, false, 300, 'Oktaax'),
            'public/'

        );

        $this->router = new Router;
    }

    protected function getServerClass(): string
    {
        return HttpServer::class;
    }


    public function setView(View $view)
    {
        $this->config->view = $view;
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
            Console::error("Certificate not found!");
            throw new InvalidResourceException("Certificate not found!");
        }

        if (!file_exists($key)) {
            Console::error("Key not found!");
            throw new InvalidResourceException("key not found!");
        }
        $this->setServer('ssl_cert_file', $cert);
        $this->setServer('ssl_key_file', $key);

        $this->config->sock_type = SWOOLE_SOCK_TCP | SWOOLE_SSL;
        $this->config->mode = SWOOLE_BASE;
        $this->protocol = 'https';

        return $this;
    }

    /**
     * Enable SSL for the server. 
     * Alternative of withSSl() method
     *
     * @return $this
     */
    public function securely($cert, $key)
    {
        return  $this->withSSL($cert, $key);
    }


    /**
     * Set the server \Swoole\Http\Server options.
     *
     * @param string|array $setting \Swoole\Http\Server settings.
     * @param mixed $value array value  
     *
     */
    public function setServer(array|string $setting, mixed $value = null)
    {
        if (is_array($setting)) {
            if (!is_null($value)) {
                Console::warning("value would'nt be save");
                trigger_error(" value would'nt be save", E_USER_WARNING);
            }
            $this->serverSettings = array_merge($this->serverSettings, $setting);
        } else {
            $this->serverSettings[$setting] = $value;
        }

        return $this;
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

        $this->config->app->key = $appkey;
        $this->config->app->csrfExp = $expr;
        $this->config->app->useCsrf = true;
    }

    /**
     * 
     * Initialization server
     * 
     * 
     */
    private function init()
    {

        $class = $this->getServerClass();
        if (!is_null($this->config->mode) && !is_null($this->config->sock_type)) {
            $this->protocol = "https";
            $this->server = new $class($this->host, $this->port, $this->config->mode, $this->config->sock_type);
        } else {
            $this->server = new $class($this->host, $this->port);
        }
    }
    /**
     * @param int $port
     * @param string|callback|null $hostOrcallback
     * @param callable|null $callback
     */

    public function listen(int $port, string|callable|null $hostOrcallback = null, ?callable $callback = null)
    {


        $this->port = $port;
        $this->host = \is_string($hostOrcallback) ? $hostOrcallback : "127.0.0.1";
        $this->app = Application::getInstance();
        if (method_exists($this, 'boot')) {
            \call_user_func([$this, 'boot']);
        }

        $this->init();


        $this->makeAGlobalServer();
        if (method_exists($this, 'eventRegistery')) {
            \call_user_func([$this, 'eventRegistery']);
        }
        $this->bootEvents();

        $this->server->set($this->serverSettings ?? []);

        $this->server->on("workerstart", function (HttpServer|WebSocketServer $server, $workerId) use ($callback, $hostOrcallback) {
            $cb = is_callable($hostOrcallback) ?
                $hostOrcallback : (is_callable($callback) && !is_callable($hostOrcallback) ?
                    $callback : null);

            (new WorkerStart(
                new URL(
                    $this->host,
                    $this->port,
                    $this->protocol,
                    method_exists($this, 'ws')
                ),
                $cb
            ))->handle(...\func_get_args());
        });

        $this->server->on('request', function ($request, $response) {
            (new EventRequest($this->config))->handle(...\func_get_args());
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
     * Reload server
     * 
     */
    public function reload()
    {
        $this->server->reload();
    }

    /**
     * 
     * Set Your Application Configuration
     * @param AppConfig $appConfig
     */
    public function setApplication(AppConfig $appConfig)
    {
        $this->config->app = $appConfig;
    }


    /**
     * Renew Oktaax Configuration
     * 
     * @param OktaaxConfig $config
     * 
     */

    public function setConfig(OktaaxConfig $config)
    {

        $this->config = $config;
    }

    public function on(string $event, callable $handler)
    {

        $handledEvents = $this->getHandledEvents();
        if (\in_array(strtolower($event), $handledEvents)) {
            throw new Error("Cannot declare handled event!");
        }

        $this->swoolevents[$event] = $handler;
    }

    public function getHandledEvents()
    {
        return ['request', 'workerstart'];
    }

    protected function makeAGlobalServer()
    {
        new ServerBag($this->server);
    }
    protected function bootEvents()
    {
        foreach ($this->swoolevents as $event => $handler) {
            $this->server->on($event, $handler);
        }
    }
    public function __call($name, $arguments)
    {
        if (method_exists($this->router, $name)) {
            return \call_user_func_array([$this->router, $name], $arguments);
        }

        throw new BadMethodCallException("Method $name does not exist on " . static::class);
    }
};
