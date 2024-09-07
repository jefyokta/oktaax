<?php

namespace Oktaax\Http;

use Error;
use Swoole\Http\Response as SwooleResponse;
use Oktaax\Http\APIResponse;
use Swoole\Coroutine;

class Response
{
    public $response;
    private $viewsdir;

    public function __construct(SwooleResponse $response, $viewsdir = null)
    {
        $this->response = $response;
        $this->header("X-Powered-By", "Oktaa");
        $this->viewsdir = $viewsdir;
    }

    public function header($key, $value)
    {
        $this->response->header($key, $value);
    }

    public function render(string $view, array $data = [])
    {
        $filePath = $this->viewsdir . "/$view.php";

        if (!is_readable($filePath)) {
            throw new \Exception("View file not found or inaccessible: $filePath");
        }

        extract($data);
        ob_start();
        include $filePath;
        $viewContent = ob_get_clean();

        $this->response->end($viewContent);
    }

    public function json(APIResponse $json)
    {
        $this->header("content-type", "Application/json");
        $this->response->end(json_encode($json->get()));
    }

    public function cookie($name, $value = null, $expires = null, $path = null, $secure = null, $httponly = null, $samesite = null, $priority = null)
    {

        $this->response->cookie($name, $value, $expires, $path, $secure, $httponly, $samesite, $priority);
    }

    public function sendfile($filename, $offset = null, $lenght = null)
    {
        $this->response->sendfile($filename, $offset, $lenght);
    }

    public function status(int $status, $reason = null)
    {
        $this->response->status($status, $reason);
    }
}
