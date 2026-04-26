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





namespace Oktaax\Http;

use Oktaax\Exception\ValidationException;
use Oktaax\Http\Support\Validation;
use Oktaax\Interfaces\Injectable;
use Swoole\Http\Request as HttpRequest;
use Stringable;

/**
 * @package Oktaax\Http
 */

class Request implements Stringable, Injectable
{

    /**
     * The current request instance.
     * 
     * @var Request
     */
    private static $instance;

    /**
     * 
     * @var ?int $fd
     * Request frame disk
     */

    public $fd = null;
    /**
     * The original Swoole HTTP Request instance.
     * 
     * @var \Swoole\Http\Request
     */

    public $request;

    /**
     * 
     * 
     * Error nn validate
     * @var array   
     */

    public $errors;




    /**
     * Additional properties storage.
     *
     * @var array
     */

    protected $attributes = [];
    /**
     * 
     * Request params
     * @var array|null
     */
    public  $params;

    public array $headers;

    private static $injection = [];


    public $uri;

    public function __construct(HttpRequest $request)
    {
        $this->request = $request;
        $this->fd = $request->fd ?? null;
        $this->uri = $request->server['request_uri'] ?? '/';
        $this->headers = $request->header;
    }
    public static function inject(string $key, $value)
    {
        self::$injection[$key] = \is_callable($value) ? $value :  new $value;
    }
    public  static function create(HttpRequest $request)
    {
        return new static($request);
    }

    /**
     * 
     * @param string $name
     * @return mixed
     */

    public function __get($name)
    {
        if (property_exists($this->request, $name)) {
            return $this->request->$name;
        }
        return $this->attributes[$name] ?? null;
    }

    /**
     * 
     * @param string $name
     * @param mixed $value
     */
    public function __set($name, $value)
    {
        $this->attributes[$name] = $value;
    }

    /**
     * 
     * @param string $name
     * @param mixed $value
     */

    public function setHeader($name, $value)
    {

        $this->headers[$name] = $value;
    }

    /**
     * 
     * @param string $name
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->request->$name) || isset($this->attributes[$name]);
    }


    /**
     * 
     * @param string $name
     */
    public function __unset($name)
    {
        unset($this->attributes[$name]);
    }

    /**
     * 
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        if (method_exists($this->request, $name)) {
            return \call_user_func_array([$this->request, $name], $arguments);
        }

        if ($injected = static::$injection[$name]) {
            return \call_user_func($injected, ...$arguments);
        }

        throw new \BadMethodCallException("Method {$name} does not exist.");
    }
    /**
     * Get a parameter (query, post, or cookie) from the request.
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function input(string $key, mixed $default = null)
    {
        if (isset($this->request->get[$key])) {
            return $this->request->get[$key];
        }

        if (isset($this->request->post[$key])) {
            return $this->request->post[$key];
        }

        if (isset($this->request->cookie[$key])) {
            return $this->request->cookie[$key];
        }

        return $default;
    }
    public function post(string $key, mixed $default = null)
    {
        return $this->request->post[$key] ?? $default;
    }

    /**
     * Get all request parameters.
     * 
     * @return array
     */
    public function all(): array
    {
        return  \array_merge(
            $this->request->get ?? [],
            $this->request->post ?? [],
            $this->request->cookie ?? []
        );
    }

    /**
     * Check if the request has a given parameter.
     * 
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->all()[$key]);
    }

    /**
     * Get header Request
     * 
     * @param string $key
     * 
     */
    public function header($key)
    {
        $key = strtolower($key);
        return $this->headers[$key] ?? null;
    }



    /**
     * Check if the request query has a given parameter.
     * 
     * @param string $key
     * @return bool
     */
    public function queryHas(string $key)
    {
        return  isset($this->request->get[$key]);
    }

    /**
     * Get the client's user agent.
     * 
     * @return string|null
     */
    public function userAgent()
    {
        return $this->request->header['user-agent'] ?? null;
    }

    /**
     * Determine if the request is a JSON request.
     * 
     * @return bool
     */
    public function isJson(): bool
    {
        return $this->headers['content-type'] !== null &&
            strpos($this->headers['content-type'], 'application/json') !== false;
    }

    /**
     * Determine if the request is a form submission.
     * 
     * @return bool
     */
    public function isFormSubmission()
    {
        return $this->isMethod('POST') && null !== $this->headers['content-type'] &&
            strpos($this->headers['content-type'], 'application/x-www-form-urlencoded') !== false;
    }

    /**
     * Get the path of the request URI.
     * 
     * @return string
     */
    public function path(): string
    {
        return parse_url($this->request->server['request_uri'] ?? '', PHP_URL_PATH) ?: '/';
    }

    /**
     * Determine if the request is requesting JSON.
     * 
     * @return bool
     */
    public function wantsJson()
    {
        return $this->headers['accept'] &&
            strpos($this->headers['accept'], 'application/json') !== false;
    }

    /**
     * Determine if the request is requesting JavaScript.
     * 
     * @return bool
     */

    public function wantsJS()
    {
        return $this->headers['accept'] &&
            strpos($this->headers['accept'], 'application/javascript') !== false;
    }

    public function protocol()
    {
        return !empty($this->request->server['https']) && $this->request->server['https'] !== 'off' ? 'https' : 'http';
    }
    /**
     * Get the host from the request.
     * 
     * @return string
     */
    public function host()
    {
        return $this->headers['host'] ?? '';
    }
    /**
     * Compare param with request method
     * 
     * @return bool
     */
    public function isMethod(string $method): bool
    {
        return strtoupper($method) === $this->request->server['request_method'];
    }

    /**
     * 
     * Validation Request
     * @param array $rules
     * @param ?array $data
     * 
     */
    public function validate(array $rules, array|null $data = null)
    {
        if (null === $data) {
            $data = $this->isJson() ? $this->json() : $this->post;
        }

        $this->errors = ($validator = new Validation)->validate($data, $rules) ?? null;

        $reqValidated = new RequestValidated($data, !empty($this->errors) ? $this->errors : null);
        if (!$reqValidated->isOk()) {
            throw new ValidationException($reqValidated->getErrors());
        }

        return $reqValidated->getData();
    }

    /**
     * Return request body
     *
     * @param string $key 
     * 
     * @return mixed
     */
    public function body(?string $key = null)
    {
        if (null == $key) {
            return $this->request->post;
        }
        return $this->json($key) ?? $this->post($key);
    }

    /**
     * 
     * Get Request Json
     * @return mixed|null 
     * 
     */
    public function json(?string $key = null)
    {
        $rawContent = $this->request->rawContent();

        $jsonStart = strpos($rawContent, '{');

        if ($jsonStart !== false) {
            $json = substr($rawContent, $jsonStart);
            $dec = json_decode($json, true);
            if ($dec) {
                return null == $key ?  $dec : $dec[$key];
            }
        }

        return null;
    }
    /**
     * 
     * @param string $name
     * 
     * @return mixed 
     */

    public function cookie($name)
    {
        return $this->request->cookie[$name] ?? null;
    }
    public static function &getInstance()
    {
        return static::$instance;
    }

    public function hasHeader($key)
    {
        return isset($this->headers[$key]);
    }
    public function isInertia()
    {
        return $this->hasHeader('x-inertia');
    }


    public function isAjax()
    {
        return null !== ($this->headers['x-request-with']) && $this->headers['x-request-with'] === 'XMLHttpRequest';
    }

    public function xhr()
    {
        return $this->isAjax();
    }


    public function __invoke($key)
    {
        $this->all()[$key];
    }

    public function bodies()
    {
        return \array_merge($this->post ?? [], (array) $this->body ?? []);
    }

    public function parameters()
    {

        return \array_merge($this->get ?? [], $this->bodies(), $this->params);
    }

    public function __toString(): string
    {
        return json_encode($this);
    }

    public function get($key, $default = null)
    {
        return $this->request->get[$key] ?? $default;
    }
}
