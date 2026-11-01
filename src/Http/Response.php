<?php

namespace Oktaax\Http;

use Oktaax\Console;
use Oktaax\Http\Response\JsonEnvelope;
use Oktaax\Application\ConnectionScope;
use Oktaax\Http\Response\SseResponse;
use Oktaax\Http\Response\StreamedResponse;
use Oktaax\Contracts\View;
use Swoole\Http\Response as SwooleResponse;

class Response
{
    protected SwooleResponse $response;

    protected int $status = 200;

    protected mixed $content = null;

    /**
     * Pending response headers normalized to lowercase names.
     *
     * @var array<string, string>
     */
    protected array $headers = [];

    private bool $preventEnd = false;

    private bool $headersCommitted = false;

    private bool $writeFailed = false;

    /**
     * Application-owned response helper callables bound by the kernel.
     *
     * @var array<string, callable>
     */
    private array $helpers = [];
    private ?View $view = null;
    private int $responseChunkSize = 8192;
    private ?ErrorPageRenderer $errorPages = null;
    private bool $debug = false;

    public function __construct(
        SwooleResponse $response,
        protected Request $request,
    ) {
        $this->response = $response;
        $this->request = $request;
        $request->watchConnection(fn() => $response->isWritable());
    }
    /**
     * Return the underlying native HTTP response.
     *
     * @return \Swoole\Http\Response
     */
    public function swoole()
    {
        return $this->response;
    }

    /**
     * Detach automatic response completion; the owning stream must finish or close it.
     *
     * @return void
     */
    public function preventEnd()
    {
        $this->preventEnd = true;
    }

    /**
     * Return whether automatic response completion is detached.
     *
     * @return bool
     */
    public function isEndPrevented()
    {
        return $this->preventEnd;
    }

    /**
     * Invoke an application-bound helper; arguments are forwarded without an implicit response parameter.
     *
     * @param string $name
     * @param array<array-key, mixed> $arguments
     * @return mixed
     * @throws \BadMethodCallException When no helper exists.
     */
    public function __call($name, $arguments)
    {
        $helper = $this->helpers[$name] ?? null;
        if ($helper === null) {
            throw new \BadMethodCallException("Method {$name} does not exist.");
        }

        return $helper(...$arguments);
    }

    /**
     * Bind helper callables owned by the request application.
     *
     * @param array<string, callable> $helpers
     * @internal
     */
    public function useHelpers(array $helpers): void
    {
        $this->helpers = $helpers;
    }
    public function useView(View $view): void
    {
        $this->view = $view;
    }
    public function useErrorPages(
        ErrorPageRenderer $renderer,
        bool $debug = false,
    ): void {
        $this->errorPages = $renderer;
        $this->debug = $debug;
    }
    public function setChunkSize(int $size): void
    {
        if ($size < 1) {
            throw new \InvalidArgumentException(
                'Response chunk size must be positive.',
            );
        }
        $this->responseChunkSize = $size;
    }
    public function hasStarted(): bool
    {
        return $this->headersCommitted;
    }
    public function connection(): ConnectionScope
    {
        return $this->request->connection();
    }
    /**
     * Observe completion or abort independently from handler completion.
     *
     * @param callable(\Oktaax\Event\Event<array{aborted: bool, disconnected: bool}>): mixed $callback
     */
    public function onResponseClosed(callable $callback): void
    {
        $this->connection()->onClose($callback);
    }
    /**
     * Create an SSE value already bound to this HTTP response.
     *
     * @param array<string, string> $headers
     */
    public function sse(array $headers = []): SseResponse
    {
        return new SseResponse($headers, $this);
    }

    public function sendHeaders(): void
    {
        $this->commitHeaders();
    }

    public function commitHeaders(): void
    {
        if ($this->headersCommitted) {
            return;
        }
        if ($this->status !== 200) {
            $this->response->status($this->status);
        }
        foreach ($this->headers as $key => $value) {
            $this->response->header($key, $value);
        }
        $this->headersCommitted = true;
    }

    public function header(string $key, string $value): static
    {
        if ($this->headersCommitted) {
            throw new \LogicException(
                'Cannot change headers after the response has started.',
            );
        }
        $this->headers[strtolower($key)] = $value;
        return $this;
    }

    public function status(int $status): static
    {
        if ($this->headersCommitted) {
            throw new \LogicException(
                'Cannot change status after the response has started.',
            );
        }
        $this->status = $status;
        return $this;
    }

    /**
     * Serialize exactly the supplied value as JSON; use jsonEnvelope() for a message/error/data envelope.
     *
     * @throws \JsonException When the supplied value cannot be encoded.
     * @throws \LogicException When headers have already been committed.
     */
    public function json(mixed $data = null): void
    {
        $this->header('content-type', 'application/json');
        if ($data instanceof JsonEnvelope) {
            $this->end($data->encode());
            return;
        }
        $this->end(json_encode($data, JSON_THROW_ON_ERROR));
    }

    /**
     * Send an explicit message/error/data envelope without interpreting field names in the payload.
     *
     * @param array<array-key, mixed> $error
     */
    public function jsonEnvelope(
        mixed $data = null,
        string $message = '',
        array $error = [],
    ): void {
        $this->json([
            'message' => $message,
            'error' => $error,
            'data' => $data,
        ]);
    }

    /**
     * Render an application-controlled view name with template variables through the bound engine.
     *
     * @param array<string, mixed> $data
     * @throws \Throwable When no engine is bound or template rendering fails.
     */
    public function render(string $view, array $data = []): void
    {
        try {
            $engine =
                $this->view ??
                throw new \LogicException(
                    'No view engine is bound to this response.',
                );
            $content = $engine->render($view, $data);
            $this->end($content);
        } catch (\Throwable $e) {
            if (!$this->hasStarted()) {
                $this->status(500);
            }

            throw $e;
        }
    }

    public function redirect(string $location, int $status = 302): void
    {
        $this->status($status)->header('Location', $location)->end();
    }

    public function back(string $default = '/'): void
    {
        $this->redirect($this->request?->header('referer') ?? $default);
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
        string $priority = '',
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
            $priority,
        );

        return $this;
    }

    /**
     * Resolve a MIME extension or use an explicit media type, then set Content-Type.
     *
     * @return static
     */
    public function type(string $type)
    {
        if (str_contains($type, '/')) {
            $this->header('content-type', $type);
            return $this;
        }

        static $mime = null;
        $mime ??= require __DIR__ . '/../Utils/MimeTypes.php';
        if (!isset($mime[$type])) {
            Console::warn("cannot found content type for $type");
        }

        $this->header('content-type', $mime[$type] ?? $type);

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

    /**
     * Commit metadata and send an application-controlled file slice; HEAD sends no file bytes.
     *
     * @return bool
     */
    public function sendfile(
        string $filename,
        int $offset = 0,
        ?int $length = null,
    ) {
        if (!$this->isWritable()) {
            $this->request->close(true, !$this->response->isWritable());
            return false;
        }
        $length ??= filesize($filename) - $offset;
        $this->commitHeaders();
        if ($this->request->isMethod('HEAD')) {
            $sent = $this->response->end();
        } else {
            $sent = $this->response->sendfile($filename, $offset, $length);
        }
        $this->request->close(!$sent);
        return $sent;
    }

    /**
     * Create a finite streamed response; stop producing data when the supplied writer returns false.
     *
     * @param \Closure(callable(string): bool): mixed $callback
     * @param array<string, string> $headers
     */
    public function stream(
        \Closure $callback,
        int $status = 200,
        array $headers = [],
    ): StreamedResponse {
        return new StreamedResponse($callback, $status, $headers);
    }

    /**
     * Write one chunk and return transport success; a failed write aborts the connection scope.
     */
    public function write(string $data): bool
    {
        if (!$this->isWritable()) {
            $this->request->close(true, !$this->response->isWritable());
            return false;
        }
        $this->commitHeaders();
        if ($this->request->isMethod('HEAD')) {
            return true;
        }
        $sent = $this->response->write($data);
        if (!$sent) {
            $this->writeFailed = true;
            $this->request?->close(true, !$this->response->isWritable());
        }
        return $sent;
    }

    /**
     * Commit metadata and finish the response, chunking large strings and stopping on failed writes.
     *
     * @return null
     */
    public function end(mixed $content = null)
    {
        if (!$this->isWritable()) {
            $this->request?->close(true, !$this->response->isWritable());
            return;
        }

        $this->content = $content;

        $this->commitHeaders();
        if ($this->request->isMethod('HEAD')) {
            $sent = $this->response->end();
            $this->request->close(!$sent);
            return;
        }
        $contentLength = \strlen($content ?? '');
        if ($contentLength <= $this->responseChunkSize) {
            $sent = $this->response->end($content);
            $this->request->close(!$sent);
            return;
        }
        if ($this->responseChunkSize < 1) {
            throw new \LogicException('Response chunk size must be positive.');
        }
        for ($i = 0; $i < $contentLength; $i += $this->responseChunkSize) {
            if (
                !$this->write(
                    substr($this->content, $i, $this->responseChunkSize),
                )
            ) {
                return;
            }
        }
        $sent = $this->response->end();
        $this->request->close(!$sent);
    }

    /**
     * Return whether the native response can no longer be written.
     *
     * @return bool
     */
    function isEnded()
    {
        return !$this->response->isWritable();
    }

    /**
     * Render a self-contained public HTTP error page with the supplied status.
     *
     * @return null
     */
    public function renderHttpError(int $code)
    {
        $this->status($code)->header(
            'content-type',
            'text/html; charset=utf-8',
        );
        return $this->end(
            ($this->errorPages ??= new ErrorPageRenderer())->http(
                $code,
                $this->request,
            ),
        );
    }

    /**
     * Render escaped diagnostics only when debug was bound by the kernel; otherwise render generic HTTP 500.
     */
    public function renderException(\Throwable $error): void
    {
        if (!$this->debug) {
            $this->renderHttpError(500);
            return;
        }
        $this->status(500)->header('content-type', 'text/html; charset=utf-8');
        $this->end(
            ($this->errorPages ??= new ErrorPageRenderer())->exception(
                $error,
                $this->request,
            ),
        );
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

    public function isWritable(): bool
    {
        return !$this->writeFailed &&
            !$this->connection()->isClosed() &&
            $this->response->isWritable();
    }

    /**
     * Return pending header metadata; cookie state is maintained by the native response.
     *
     * @return array<string, string>
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    public function upgrade()
    {
        return $this->response->upgrade();
    }

    public function recv()
    {
        return $this->response->recv();
    }

    public function push(
        $data,
        $opcode = SWOOLE_WEBSOCKET_OPCODE_TEXT,
        $flag = SWOOLE_WEBSOCKET_FLAG_FIN,
    ) {
        return $this->response->push($data, $opcode, $flag);
    }

    public function close()
    {
        return $this->response->close();
    }
}
