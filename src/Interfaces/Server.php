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
 




 namespace Oktaax\Interfaces;
 
 use Oktaax\Http\Request;
 use Oktaax\Http\Response as OktaResponse;
use Oktaax\Oktaax;

 interface Server
 {
     /**
      * Set view configuration.
      *
      * @param string $viewDir
      * @param 'blade'|'php' $render_engine
      * @return static
      */
     public function setView(string $viewDir, string $render_engine);
 
     /**
      * Enable SSL for the server.
      *
      * @param string $cert
      * @param string $key
      * @return static
      */
     public function withSSL(string $cert, string $key);
 
     /**
      * Alternative method to enable SSL.
      *
      * @param string $cert
      * @param string $key
      * @return static
      */
     public function securely(string $cert, string $key);
 
     /**
      * Set server settings.
      *
      * @param string|array $setting
      * @param mixed $value
      */
     public function setServer(string|array $setting, mixed $value = null);
 
     /**
      * Enable CSRF protection.
      *
      * @param string $appkey
      * @param int $expr
      */
     public function useCsrf(string $appkey, int $expr = 300);
 
     /**
      * Define a GET route.
      *
      * @param string $path
      * @param string|callable|array $callback
      * @param string|callable|array ...$middlewares
      * @return static
      */
     public function get(string $path, string|callable|array $callback, string|callable|array ...$middlewares);
 
     /**
      * Define a POST route.
      *
      * @param string $path
      * @param string|callable|array $callback
      * @param string|callable|array ...$middlewares
      * @return static
      */
     public function post(string $path, string|callable|array $callback, string|callable|array ...$middlewares);
 
     /**
      * Define a PUT route.
      *
      * @param string $path
      * @param string|callable|array $callback
      * @param string|callable|array ...$middlewares
      * @return static
      */
     public function put(string $path, string|callable|array $callback, string|callable|array ...$middlewares);
 
     /**
      * Define a DELETE route.
      *
      * @param string $path
      * @param string|callable|array $callback
      * @param string|callable|array ...$middlewares
      * @return static
      */
     public function delete(string $path, string|callable|array $callback, string|callable|array ...$middlewares);
 
     /**
      * Define a PATCH route.
      *
      * @param string $path
      * @param string|callable|array $callback
      * @param string|callable|array ...$middlewares
      * @return static
      */
     public function patch(string $path, string|callable|array $callback, string|callable|array ...$middlewares);
 
     /**
      * Define an OPTIONS route.
      *
      * @param string $path
      * @param string|callable|array $callback
      * @param string|callable|array ...$middlewares
      * @return static
      */
     public function options(string $path, string|callable|array $callback, string|callable|array ...$middlewares);
 
     /**
      * Define a HEAD route.
      *
      * @param string $path
      * @param string|callable|array $callback
      * @param string|callable|array ...$middlewares
      * @return static
      */
     public function head(string $path, string|callable|array $callback, string|callable|array ...$middlewares);
 
     /**
      * Register global middleware.
      *
      * @param callable $globalMiddleware
      * @return static
      */
     public function use(callable $globalMiddleware);


     public function path(string $path, Oktaax $app);


     public function getRoutes();
 
    
 }
 