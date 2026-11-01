<?php

namespace Oktaax\Http\Response;

class File
{
    /**
     * Describe an application-controlled file path and response metadata.
     *
     * @param array<string, string> $headers
     */
    public function __construct(
        private string $filePath,
        public readonly int $status = 200,
        private $headers = [],
    ) {}

    /**
     * Return the application-controlled file path for native file delivery.
     *
     * @return string
     */
    public function getFilePath()
    {
        return $this->filePath;
    }

    /**
     * Return headers to commit before the body or producer starts.
     *
     * @return array<string, string>
     */
    public function getHeaders()
    {
        return $this->headers;
    }
    /**
     * Add or replace file-response metadata before it is emitted.
     *
     * @return static
     */
    public function withHeader(string $key, string $value)
    {
        $this->headers[$key] = $value;
        return $this;
    }
}
