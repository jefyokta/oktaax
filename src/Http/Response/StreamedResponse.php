<?php

namespace Oktaax\Http\Response;

use Closure;

class StreamedResponse
{
    /**
     * Finite stream producer; a false write result signals the producer to stop.
     *
     * @var \Closure(callable(string): bool): mixed
     */
    protected Closure $callback;

    /**
     * HTTP status
     */
    protected int $status;

    /**
     * Headers committed before invoking the stream producer.
     *
     * @var array<string, string>
     */
    protected array $headers;

    /**
     * Describe a finite producer; it receives a boolean-returning writer for each output chunk.
     *
     * @param array<string, string> $headers
     * @param \Closure(callable(string): bool): mixed $callback
     */
    public function __construct(
        Closure $callback,
        int $status = 200,
        array $headers = [],
    ) {
        $this->callback = $callback;
        $this->status = $status;
        $this->headers = $headers;
    }

    /**
     * Return the producer without executing it.
     *
     * @return \Closure(callable(string): bool): mixed
     */
    public function getCallback(): Closure
    {
        return $this->callback;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * Return headers to commit before the body or producer starts.
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }
}
