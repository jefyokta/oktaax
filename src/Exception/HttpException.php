<?php

namespace Oktaax\Exception;

class HttpException extends \RuntimeException
{
    /**
     * Describe an HTTP status, optional internal message, and response headers such as Allow.
     *
     * @param array<string, string> $headers
     */
    public function __construct(
        private int $statusCode,
        ?string $message = null,
        private array $headers = [],
    ) {
        parent::__construct($message ?? '', $statusCode);
    }

    /**
     * Return response metadata that custom exception handlers should preserve.
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Return the response status carried by this exception.
     *
     * @return int
     */
    function getStatusCode()
    {
        return $this->statusCode;
    }
    /**
     * Return the exception message; the default public HTTP page uses a standard status explanation.
     *
     * @return string
     */
    function getHttpMessage()
    {
        return $this->message;
    }
}
