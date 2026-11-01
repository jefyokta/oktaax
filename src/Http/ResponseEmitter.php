<?php

namespace Oktaax\Http;

use Generator;
use JefyOkta\PhpPromise\Promise;
use Oktaax\Contracts\Jsonable;
use Oktaax\Http\Response\File;
use Oktaax\Http\Response\JsonEnvelope;
use Oktaax\Http\Response\SseResponse;
use Oktaax\Http\Response\StreamedResponse;
use Stringable;

class ResponseEmitter
{
    /**
     * Registered returned-value emitters indexed by exact class/interface or scalar type name.
     *
     * @var array<string, \Closure(mixed, \Oktaax\Http\Request, \Oktaax\Http\Response): mixed>
     */
    private array $handlers = [];
    /**
     * Register an emitter that sends its own response for a returned value type.
     *
     * @param \Closure(mixed, \Oktaax\Http\Request, \Oktaax\Http\Response): mixed $handler
     */
    public function register(string $type, \Closure $handler): void
    {
        $this->handlers[$type] = $handler;
    }

    /**
     * Emit supported values and streams, respecting ended or explicitly detached responses.
     *
     * @throws \RuntimeException When a return type or yielded chunk cannot be emitted.
     * @throws \Throwable When a producer, codec, or custom emitter fails.
     */
    public function dispatch(mixed $result, Request $req, Response $res): void
    {
        if (!$res->isWritable() || $res->isEndPrevented()) {
            return;
        }
        if ($result instanceof Promise) {
            $this->dispatch(await($result), $req, $res);
            return;
        }
        if (is_string($result)) {
            $res->end($result);
            return;
        }
        if ($result === null) {
            if (!$res->hasStarted()) {
                $res->header('x-no-content', '1');
            }
            $res->end();
            return;
        }
        if ($result instanceof Generator) {
            foreach ($result as $chunk) {
                if (
                    $chunk !== null &&
                    !is_scalar($chunk) &&
                    !($chunk instanceof Stringable)
                ) {
                    throw new \RuntimeException(
                        'Unsupported generator content type: ' .
                            get_debug_type($chunk),
                    );
                }
                if (!$res->write((string) $chunk)) {
                    return;
                }
            }
            $res->end($result->getReturn());
            return;
        }
        if (is_array($result) || $result instanceof \Traversable) {
            $res->json(
                $result instanceof \Traversable
                    ? iterator_to_array($result)
                    : $result,
            );
            return;
        }
        if ($result instanceof Jsonable) {
            $res->json($result->toJson());
            return;
        }
        $type = is_object($result) ? $result::class : gettype($result);
        if (isset($this->handlers[$type])) {
            $this->handlers[$type]($result, $req, $res);
            return;
        }
        if (is_object($result)) {
            foreach ($this->handlers as $key => $handler) {
                if (is_a($result, $key)) {
                    $handler($result, $req, $res);
                    return;
                }
            }
        }
        if ($result instanceof SseResponse) {
            $result->start($res);
            return;
        }
        if ($result instanceof StreamedResponse) {
            $res->status($result->getStatus());
            foreach ($result->getHeaders() as $name => $value) {
                $res->header($name, $value);
            }
            $result->getCallback()(fn($data) => $res->write($data));
            if ($res->isWritable()) {
                $res->end();
            }
            return;
        }
        if ($result instanceof File) {
            $res->status($result->status);
            foreach ($result->getHeaders() as $name => $value) {
                $res->header($name, $value);
            }
            $res->sendfile($result->getFilePath());
            return;
        }
        if ($result instanceof JsonEnvelope) {
            $res->json($result);
            return;
        }
        throw new \RuntimeException('Unhandled return type: ' . $type);
    }
}
