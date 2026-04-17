<?php

namespace Oktaax\Http;

use Oktaax\Console;
use Oktaax\Contracts\JsonScheme;
use Oktaax\Core\Application;
use Oktaax\Http\Support\StreamedResponse;
use Oktaax\Interfaces\Injectable;
use Oktaax\Interfaces\View;
use Oktaax\Types\OktaaxConfig;
use Swoole\Coroutine\Http\Server;
use Swoole\Http\Response as SwooleResponse;

class Response implements Injectable
{

    protected SwooleResponse $response;

    protected OktaaxConfig $config;

    protected int $status = 200;

    protected mixed $content = null;

    protected Headers $headers;

    protected static array $injected = [];
    private static $chunkSize = 8192;

    public function __construct(
        SwooleResponse $response,
    ) {

        $this->response = $response;

        $this->headers = new Headers(["x-powered-by" => "oktaax"]);
    }

    public static function inject(string $name, $handler): void
    {
        self::$injected[$name] = is_callable($handler)
            ? $handler
            : new $handler;
    }

    public  static function setChunkSize(int $chunkSize): void
    {
        self::$chunkSize = $chunkSize;
    }

    public function __call($name, $arguments)
    {

        if (!isset(self::$injected[$name])) {
            throw new \BadMethodCallException("Method {$name} does not exist.");
        }

        return \call_user_func(self::$injected[$name], ...$arguments);
    }


    public function header(string $key, string $value): static
    {
        $this->headers->set($key, $value);
        return $this;
    }

    public function status(int $status): static
    {
        $this->status = $status;
        return $this;
    }


    public function json(mixed $data = null, string $message = '', array $error = []): void
    {

        $this->headers->set('Content-Type', 'application/json');

        if ($data instanceof JsonScheme) {
            $this->end($data->encode());
            return;
        }

        if (is_array($data) && isset($data['message']) && isset($data['error']) && isset($data['data'])) {
            $this->end(json_encode($data));
            return;
        }

        if (is_array($data) && isset($data['message']) && !isset($data['error']) && !isset($data['data'])) {
            $this->end(json_encode($data));
            return;
        }

        $json = new JsonScheme(
            is_array($data) ? $data : [$data],
            $message,
            $error
        );

        $this->end($json->encode());
    }


    public function render(string $view, array $data = []): void
    {

        try {
            $content =  Application::warm(View::class)
                ->render($view, $data);
            $this->end($content);
        } catch (\Throwable $e) {

            $this->status(500);

            throw $e;
        }
    }


    public function redirect(string $location, int $status = 302): void
    {

        $this->status($status)
            ->header('Location', $location)
            ->end();
    }

    public function back(string $default = '/'): void
    {

        $this->redirect(
            Application::getRequest()->header("referer") ?? $default
        );
    }


    public function cookie(
        string $name,
        string $value = '',
        int $expires = 0,
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httponly = false,
        string $samesite = 'Lax',
        string $priority = ''
    ): static {

        $this->response->cookie(
            $name,
            $value,
            $expires,
            $path,
            $domain,
            $secure,
            $httponly,
            $samesite,
            $priority
        );

        return $this;
    }

    public function type(string $type)
    {
        if (str_contains($type, "/")) {
            $this->header("content-type", $type);
            return $this;
        }

        $mime = require_once __DIR__ . "/../Utils/MimeTypes.php";
        if (!isset($mime[$type])) {
            Console::warn("cannot found content type for $type");
        }

        $this->header("content-type", $mime[$type] ?? $type);

        return $this;
    }


    public function with(string $message, ?int $expires = null): static
    {

        $expires ??= time() + 5;

        return $this->cookie('X-Message', $message, $expires);
    }

    public function withError(string $message, ?int $expires = null): static
    {

        $expires ??= time() + 5;

        return $this->cookie('X-ErrMessage', $message, $expires);
    }


    public function sendfile(
        string $filename,
        int $offset = 0,
        ?int $length = null
    ) {

        $length ??= filesize($filename) - $offset;

        return $this->response->sendfile(
            $filename,
            $offset,
            $length
        );
    }


    public function stream(
        \Closure $callback,
        int $status = 200,
        array $headers = []
    ): StreamedResponse {

        return new StreamedResponse(
            $callback,
            $status,
            $headers
        );
    }


    public function write(string $data)
    {

        return $this->response->write($data);
    }


    public function end(mixed $content = null)
    {

        if (!$this->response->isWritable()) {
            return;
        }

        $this->content = $content;

        $this->headers->forEach(function ($value, $key) {

            $this->response->header($key, $value);
        });

        $this->response->status($this->status);
        $contentLength = \strlen($this->content ?? '');
        if (self::$chunkSize >= $contentLength) {
            $this->response->end($content);
            return;
        }

        for ($i = 0; $i < $contentLength; $i += self::$chunkSize) {
            $this->write(substr($this->content, $i, self::$chunkSize));
        }
    }

    function isEnded()
    {
        return !$this->response->isWritable();
    }


    public function renderHttpError(int $code)
    {

        $this->status($code);

        $title = require __DIR__ . '/../Utils/HttpError.php';

        ob_start();

        $req =  Application::getRequest();
        $status = $this->status;
        $title = $title[$code] ?? $code;

        require __DIR__ . '/../Views/HttpError/index.php';

        $content = ob_get_clean();

        return $this->end($content);
    }


    public function getContent(): mixed
    {
        return $this->content;
    }

    public function getSwooleResponse(): SwooleResponse
    {
        return $this->response;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function isWritable()
    {
        return $this->response->isWritable();
    }

    public function getHeaders()
    {
        return $this->headers->all();
    }

    public function upgrade()
    {
        if (!is_subclass_of($serverClass = Application::server()::class, Server::class)) {
            Console::warn("cannot upgrade with %s . only can upgrade request with %s", $serverClass, Server::class);
            return;
        }
        return  $this->response->upgrade();
    }
}
