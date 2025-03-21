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

use OpenSwoole\Http\Response as SwooleResponse;
use Oktaax\Http\ResponseJson;
use Oktaax\Interfaces\View;
use Oktaax\Types\OktaaxConfig;

/**
 * Class Response
 * Handles HTTP responses for the Oktaax application.
 *
 * @package Oktaax\Http
 */
class Response
{
    /**
     * @var SwooleResponse The Swoole HTTP response instance.
     */
    public $response;

    /**
     * @var OktaaxConfig  Configuration from application for the response.
     */
    private OktaaxConfig  $config;

    /**
     * @var int HTTP status code for the response.
     */
    public $status = 200;


    /**
     * @var Response instance of this classs
     */
    private static $instance;

    private View $view;

    public Request $request;


    /**
     * Response constructor.
     *
     * @param SwooleResponse $response The Swoole HTTP response object.
     * @param OktaaxConfig  $config Optional configuration settings for the response.
     */
    public function __construct(SwooleResponse $response, Request $request, OktaaxConfig $config)
    {
        $this->response = $response;
        $this->header("X-Powered-By", "Oktaax");
        $this->config = $config;
        $this->view  = $this->config->view;
        $this->request = $request;
        self::$instance = &$this;
    }

    /**
     * Set an HTTP header.
     *
     * @param string $key The header key.
     * @param string $value The header value.
     */
    public function header($key, $value): Response
    {
        if ($this->response->isWritable()) {
            $this->response->header($key, $value);
        }
        return $this;
    }

    /**
     * Render a view using the specified rendering engine.
     *
     * @param string $view The view file to render (without extension).
     * @param array $data Data to pass to the view.
     * @throws \Exception If the views or cache directory cannot be created.
     * @throws \Throwable If an error occurs during rendering.
     */
    public function render(string $view, array $data = [])
    {
        try {
            return $this
            ->end(
                $this->view
                    ->render($view, $data)
            );
        } catch (\Throwable $th) {
            $this->status(500);
            throw $th;
        }
   
    }

    /**
     * Send a JSON response.
     *
     * @param ResponseJson $json The JSON response object.
     */
    public function json(ResponseJson $json)
    {
        $this->header("Content-Type", "application/json");
        $this->response->end(json_encode($json->get()));
    }

    /**
     * Set a cookie.
     *
     * @param string $name The name of the cookie.
     * @param string|null $value The value of the cookie.
     * @param int|null $expires Expiration time as a Unix timestamp.
     * @param string|null $path The path on the server in which the cookie will be available on.
     * @param bool|null $secure Whether the cookie should be sent over a secure connection.
     * @param bool|null $httponly Whether the cookie is accessible only through the HTTP protocol.
     * @param string|null $samesite SameSite attribute of the cookie.
     * @param string|null $priority The priority attribute of the cookie.
     */
    public function cookie($name, $value = '', $expires = 0, $path = '', $domain = '', $secure = false, $httponly = false, $samesite = true, $priority = '')
    {
        if ($this->response->isWritable()) {
            $samesite =  $samesite === null ? "Lax" : $samesite;
            $this->response->cookie($name, $value, $expires, $path, $domain, $secure, $httponly, $samesite, $priority);
        }
    }

    /**
     * Send a file as a response.
     *
     * @param string $filename The filename of the file to be sent.
     * @param int|null $offset The offset at which to start sending the file.
     * @param int|null $length The length of the content to send.
     */
    public function sendfile(string $filename, int $offset = 0, ?int $length = 0)
    {
        if ($length === null) {
            $length = filesize($filename) - $offset;
        }
        return  $this->response->sendfile($filename, $offset, $length);
    }

    /**
     * Set the HTTP status code for the response.
     *
     * @param int $status The HTTP status code.
     * 
     * @return Response || void
     */
    public function status(int $status)
    {
        $this->status = $status;
        return $this;
    }

    /**
     * Send Http status code to client
     * 
     * @param int $statusCode The HTTP status code.
     * 
     * @return bool
     * 
     */
    public function sendStatus(int $statusCode)
    {
        $this->status = $statusCode;
        return $this->response->status($statusCode);
    }

    /**
     * End the response.
     *
     * @param mixed $content The content to send in the response.
     * 
     * @final
     * 
     */
    public function end(mixed $content = null)
    {
        if ($this->response->isWritable()) {
            $this->response->status($this->status);
            return  $this->response->end($content);
        }
    }

    /**
     * 
     * Http redirect
     * 
     * @param string $location
     * 
     */
    public function redirect(string $location)
    {
        $this->response->redirect($location, 302);
    }

    /**
     * 
     * Set coookie for flash message.cookie name: x-message
     * 
     * @param string $msg
     * @param int|null $expires
     * 
     * @return static
     * 
     */

    public function with(string $msg, ?int $expires = null): static
    {
        if (is_null($expires)) {
            $expires = time() + 5;
        }
        $this->cookie("X-Message", $msg, $expires);
        return $this;
    }

    /**
     * 
     * Set coookie for flash error message.cookie name: x-errmessage
     * 
     * @param string $msg
     * @param int|null $expires
     * 
     * @return static
     * 
     */


    public function withError(string $errorMessage, ?int $expires = null): static
    {

        if (is_null($expires)) {
            $expires = time() + 5;
        }
        $this->cookie("X-ErrMessage", $errorMessage, $expires);
        return $this;
    }

    /**
     * 
     * 
     * rederirect back
     * 
     * @param string $default
     * 
     */

    public function back($default = '/')
    {
        $this->redirect($this->request->header['referer'] ?? $default);
    }


    /**
     * 
     * Render http error
     * 
     * @param int $code
     * 
     * @return string
     * 
     */
    public function renderHttpError(int $code)
    {
        $this->status($code);
        $title = require __DIR__ . "/../Utils/HttpError.php";
        ob_start();
        $req = $this->request;
        $status = $this->status;
        $title = $title[$status];
        require __DIR__ . "/../Views/HttpError/index.php";
        $content = ob_get_clean();
        return $this->end($content);
    }


    public static function &getInstance()
    {
        return self::$instance;
    }


    public function write(string $data)
    {
        return $this->response->write($data);
    }
}
