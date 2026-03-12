<?php

namespace Oktaax\Http\Support;

use Closure;

class StreamedResponse
{
    /**
     * Callback yang menghasilkan stream
     */
    protected Closure $callback;

    /**
     * HTTP status
     */
    protected int $status;

    /**
     * Headers
     */
    protected array $headers;

    public function __construct(Closure $callback, int $status = 200, array $headers = [])
    {
        $this->callback = $callback;
        $this->status = $status;
        $this->headers = $headers;
    }

    public function getCallback(): Closure
    {
        return $this->callback;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    
}