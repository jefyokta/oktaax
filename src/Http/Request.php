<?php

namespace Oktaax\Http;

use Swoole\Http\Request as HttpRequest;

class Request
{
    /**
     * The original Swoole HTTP Request instance.
     * 
     * @var \Swoole\Http\Request
     */


    public $request;

    /**
     * 
     * @var array   
     */

    public $requestErrors;

    /**
     * Additional properties storage.
     *
     * @var array
     */
    protected $attributes = [];

    public function __construct(HttpRequest $request)
    {
        $this->request = $request;
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
            return call_user_func_array([$this->request, $name], $arguments);
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
    public function input(string $key, $default = null)
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
    public function post(string $key)
    {
        return $this->request->post[$key] ?? null;
    }

    /**
     * Get all request parameters.
     * 
     * @return array
     */
    public function all(): array
    {
        return array_merge(
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
        return isset($this->request->get[$key]) ||
            isset($this->request->post[$key]) ||
            isset($this->request->cookie[$key]);
    }

    /**
     * Check if the request body has a given parameter.
     * 
     * @param string $key
     * @return bool
     */
    public function bodyHas(string $key)
    {
        return  isset($this->request->post[$key]);
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
        return isset($this->request->header['content-type']) &&
            strpos($this->request->header['content-type'], 'application/json') !== false;
    }

    /**
     * Determine if the request is a form submission.
     * 
     * @return bool
     */
    public function isFormSubmission()
    {
        return $this->isMethod('POST') && isset($this->request->header['content-type']) &&
            strpos($this->request->header['content-type'], 'application/x-www-form-urlencoded') !== false;
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
        return isset($this->request->header['accept']) &&
            strpos($this->request->header['accept'], 'application/json') !== false;
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
        return $this->request->header['host'] ?? '';
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
    public function validate(array $rules, array|null $data = null): bool
    {
        if (is_null($data)) {
            $data = $this->request['post'];
        }
        foreach ($rules as $key => $rule) {
            $rule = explode("|", $rule);
        }

        return true;
    }
}
