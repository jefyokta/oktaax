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

use InvalidArgumentException;
use Swoole\Http\Request as HttpRequest;


/**
 * @package Oktaax\Http
 */

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
     * 
     * Error nn validate
     * @var array   
     */

    public $errors;


    /**
     * 
     * Request Body
     * @var array
     * 
     */

    public  $body;

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

    public function __construct(HttpRequest $request)
    {
        $this->request = $request;
        $this->body = $this->request->rawContent();
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
    public function post(string $key)
    {
        return $this->request->post[$key] ?? null;
    }

    /**
     * Get all request parameters.
     * 
     * @return array
     */
    public function all(): object
    {
        return (object) array_merge(
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
     * Get header Request
     * 
     * @param string $key
     * 
     */
    public function header($key)
    {

        return $this->request->header[$key];
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

    /**
     * 
     * Validation Request
     * @param array $rules
     * @param ?array $data
     * 
     */
    public function validate(array $rules, array|null $data = null)
    {
        if (is_null($data)) {
            $data = $this->request['post'];
        }

        $errors = [];

        foreach ($rules as $key => $rule) {
            $ruleSet = explode("|", $rule);
            foreach ($ruleSet as $ruleItem) {
                if ($ruleItem === "nullable") {
                    isset($data[$key]) ?: $data[$key] = null;
                }
                if ($ruleItem === 'required' && (!isset($data[$key]) || empty($data[$key]))) {
                    $errors[$key]['required'] = "$key is required";
                }

                if (isset($data[$key])) {
                    if ($ruleItem === 'email'  && !filter_var($data[$key], FILTER_VALIDATE_EMAIL)) {
                        $errors[$key]['email'] = "$key must be a valid email address";
                    }

                    if ($ruleItem === 'numeric'  && !is_numeric($data[$key])) {
                        $errors[$key]['numeric'] = "$key must be a numeric value";
                    }

                    if ($ruleItem === 'alpha'  && !ctype_alpha($data[$key])) {
                        $errors[$key]['alpha'] = "$key must contain only alphabetic characters";
                    }

                    if ($ruleItem === 'alpha_num'  && !ctype_alnum($data[$key])) {
                        $errors[$key]['alpha_num'] = "$key must contain only letters and numbers";
                    }

                    if (str_starts_with($ruleItem, 'min:')) {
                        $minLength = explode(":", $ruleItem)[1];
                        if (!is_numeric($minLength)) throw new InvalidArgumentException("Cannot set minimum's rule with a non-numeric!");
                        if (isset($data[$key]) && strlen($data[$key]) < $minLength) {
                            $errors[$key]['min'] = "$key must be at least $minLength characters";
                        }
                    }

                    if (str_starts_with($ruleItem, 'max:')) {
                        $maxLength = explode(":", $ruleItem)[1];
                        if (!is_numeric($minLength)) throw new InvalidArgumentException("Cannot set maximum's rule with a non-numeric!");

                        if (isset($data[$key]) && strlen($data[$key]) > $maxLength) {
                            $errors[$key]['max'] = "$key must not exceed $maxLength characters";
                        }
                    }

                    if (str_starts_with($ruleItem, 'between:')) {
                        [$min, $max] = explode(",", explode(":", $ruleItem)[1]);
                        if (isset($data[$key]) && (strlen($data[$key]) < $min || strlen($data[$key]) > $max)) {
                            $errors[$key]['between'] = "$key must be between $min and $max characters";
                        }
                    }

                    if ($ruleItem === 'confirmed') {
                        if (!isset($data[$key . "_confirmation"])) {
                            $errors[$key]['confirmed'] = "{$key}_confirmation not found";
                        } elseif ($data[$key] !== $data[$key . '_confirmation']) {
                            $errors[$key]['confirmed'] = "{$key}_confirmation does not match";
                        }
                    }

                    if (str_starts_with($ruleItem, 'in:')) {
                        $allowedValues = explode(",", explode(":", $ruleItem)[1]);
                        if (isset($data[$key]) && !in_array($data[$key], $allowedValues)) {
                            $errors[$key]['in'] = "$key must be one of the following: " . implode(", ", $allowedValues);
                        }
                    }

                    if ($ruleItem === 'url'  && !filter_var($data[$key], FILTER_VALIDATE_URL)) {
                        $errors[$key]['url'] = "$key must be a valid URL";
                    }

                    if ($ruleItem === 'boolean'  && !is_bool(filter_var($data[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE))) {
                        $errors[$key]['boolean'] = "$key must be true or false";
                    }
                }
            }
        }
        $this->errors = $errors;

        return new RequestValidate($data, !empty($errors) ? $errors : null);
    }

    /**
     * Return request body
     *
     * @param string $key 
     * 
     * @return mixed
     */
    public function body(string $key)
    {
        return $this->json($key) ?? $this->post($key);
    }

    /**
     * 
     * Get Request Json
     * @return mixed|null 
     * 
     */
    public function json(string $key)
    {
        $rawContent = $this->request->rawContent();

        $jsonStart = strpos($rawContent, '{');

        if ($jsonStart !== false) {
            $json = substr($rawContent, $jsonStart);
            $dec = json_decode($json, true);
            if ($dec) {
                return $dec[$key];
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
}
