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


use Error;
use Oktaax\Http\Middleware\Csrf;
use Oktaax\Http\Request;
use Oktaax\Http\Response as OktaResponse;
use Oktaax\Interfaces\Server;
use Oktaax\Interfaces\WithBlade;
use Oktaax\Trait\Requestable;
use Oktaax\Types\AppConfig;
use Oktaax\Types\BladeConfig;
use Oktaax\Types\OktaaxConfig;
use OpenSwoole\Http\Server as HttpServer;
use Symfony\Component\Translation\Exception\InvalidResourceException;

/**
 * 
 * A class to make a raw http application server
 * 
 * @package Oktaax
 * 
 * 
 */


class Oktaax implements Server, WithBlade
{
    use Requestable;
    /**
     * Swoole HTTP server instance.
     *
     * @var \OpenSwoole\WebSocket\Server
     * 
     */

    protected $server;

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

    protected string $protocol = 'http';

    /**
     * 
     * Middleware path
     * @var array
     * 
     */
    private  array $pathMiddlewares;


    /** 
     * Set view configuration
     * 
     * @param string $viewDir
     * @param 'blade'|'php' $render_engine
     * 
     * @return static
     * 
     */

    public function __construct()
    {

        $this->config = new OktaaxConfig(
            "views/",
            "php",
            'log',
            false,
            null,
            null,
            new AppConfig(null, false, 300, 'Oktaax'),
            new BladeConfig('views/', 'views/cache', null),
         
        );
    }

    public function setView($viewDir, $render_engine)
    {
        $this->config->viewDir = $viewDir;
        $this->config->render_engine = $render_engine;
        return $this;
    }

    /** 
     * Set blade  configuration
     * 
     * @param BladeConfig $bladeConfig

     * @return static
     * 
     */
    public function blade(
        BladeConfig $bladeConfig
    ): static {
        $this->config->viewDir = $bladeConfig->viewDir;
        $this->config->render_engine = "blade";
        $this->config->blade->cacheDir = $bladeConfig->cacheDir;
        $this->config->blade->functionDir = $bladeConfig->functionDir;
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

        $this->withSSL($cert, $key);
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
            $this->serverSettings = $setting;
        } else {
            $this->serverSettings[$setting] = $value;
        }
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
        if (!is_null($this->config->mode) && !is_null($this->config->sock_type)) {
            $this->protocol = "https";
            $this->server = new HttpServer($this->host, $this->port, $this->config->mode, $this->config->sock_type);
        } else {
            $this->server = new HttpServer($this->host, $this->port);
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

        $this->host = is_string($hostOrcallback) ? $hostOrcallback : "127.0.0.1";

        $this->init();

        $this->server->set($this->serverSettings);



        if ($this->config->app->useCsrf) {
            $this->use(Csrf::generate($this->config->app->key, $this->config->app->csrfExp));
            $this->use(Csrf::handle($this->config->app->key));
        }
        if (is_callable($hostOrcallback)) {
            $hostOrcallback($this->protocol . "://" . $this->host . ":" . $this->port);
        } elseif (is_callable($callback) && !is_callable($hostOrcallback)) {
            $callback($this->protocol . "://" . $this->host . ":" . $this->port);
        }
        $this->makeAGlobalServer();
        if (method_exists($this, 'eventRegistery')) {
            call_user_func([$this, 'eventRegistery']);
        }
        $this->bootEvents();
        $this->onRequest();

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
            foreach ($this->routes as $route => &$val):
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

    public function getRoutes()
    {
        return $this->routes;
    }


    public function on(string $event, callable $handler)
    {

        $handledEvents = $this->getHandledEvents();
        if (in_array(strtolower($event), $handledEvents)) {
            throw new Error("Cannot declare handled event!");
        }

        $this->swoolevents[$event] = $handler;
    }

    public function getHandledEvents()
    {
        return ['request'];
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
};
