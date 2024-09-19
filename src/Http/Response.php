<?php

namespace Oktaax\Http;

use Oktaax\Blade\Blade;
use Swoole\Http\Response as SwooleResponse;
use Oktaax\Http\APIResponse;

class Response
{
    public $response;
    private array $config;

    public function __construct(SwooleResponse $response, array $config = [])
    {
        $this->response = $response;
        $this->header("X-Powered-By", "Oktaax");
        $this->config = $config;
    }

    public function header($key, $value)
    {
        $this->response->header($key, $value);
    }


    public function render(string $view, array $data = [])
    {
        $viewsDir = $this->config['viewsDir'];
        $cacheDir = $this->config['blade']['cacheDir'] ?? $viewsDir . "/cache";

        if (!is_dir($viewsDir)) {
            if (!mkdir($viewsDir, 0755, true)) {
                throw new \Exception("Error while creating: $viewsDir \n");
            }
        }

        if (!is_dir($cacheDir)) {
            if (!mkdir($cacheDir, 0755, true)) {
                throw new \Exception("Error while creating: $cacheDir \n");
            }
        }

        if ($this->config['render_engine'] === 'blade') {
            try {
                $blade = new Blade($viewsDir, $cacheDir);
                $viewContent = $blade->render($view, $data);
                $this->response->header("Content-Type", "text/html");
                $this->response->end($viewContent);
            } catch (\Throwable $th) {
                $this->response->status(500);
                
                throw $th;
            }
        } else {
            try {
                $viewFile = $viewsDir . '/' . $view . '.php';

                if (file_exists($viewFile)) {
                    ob_start();
                    extract($data);
                    include $viewFile;
                    $viewContent = ob_get_clean();
                    $this->response->header("Content-Type", "text/html");
                    $this->response->end($viewContent);
                } else {
                    $this->response->status(404);
                    $this->response->end("View file not found.");
                }
            } catch (\Throwable $th) {
                $this->response->status(500);
                $this->response->end("error: " . $th->getMessage() . " file: " . $th->getFile() . " line: " . $th->getLine());
            }
        }
    }



    public function json(APIResponse $json)
    {
        $this->header("Content-Type", "application/json");
        $this->response->end(json_encode($json->get()));
    }

    public function cookie($name, $value = null, $expires = null, $path = null, $secure = null, $httponly = null, $samesite = null, $priority = null)
    {
        $this->response->cookie($name, $value, $expires, $path, $secure, $httponly, $samesite, $priority);
    }

    public function sendfile($filename, $offset = null, $length = null)
    {
        $this->response->sendfile($filename, $offset, $length);
    }

    public function status(int $status)
    {
        if ($this->response->isWritable()) {
            $this->response->status($status);
        }
    }
}
