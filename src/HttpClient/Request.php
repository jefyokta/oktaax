<?php

namespace Oktaax\HttpClient;

use Oktaax\Http\Headers;

class Request
{
    public string $method;
    public Headers $headers;
    public string $body = '';

    public function __construct(
        private string $path,
        private string $host,
        RequestOptions $options,
    ) {
        $this->method = strtoupper($options->method);
        $this->headers = new Headers($options->headers);

        $this->headers->set('host', $host);

        if (isset($options->body)) {
            $this->body = is_array($options->body)
                ? http_build_query($options->body)
                : $options->body;
        }
    }

    public function __toString(): string
    {
        return "{$this->method} {$this->path} HTTP/1.1\r\n" .
            $this->buildRawHeaders() .
            "\r\n" .
            $this->body;
    }

    private function buildRawHeaders(): string
    {
        $headers = '';
        foreach ($this->headers->entries() as [$key, $value]) {
            $headers .= $key . ': ' . $value . "\r\n";
        }

        return $headers;
    }
}
