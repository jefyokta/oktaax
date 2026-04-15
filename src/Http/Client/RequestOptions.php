<?php

namespace Oktaax\Http\Client;

use Oktaax\Http\Headers;

class RequestOptions
{
    public function __construct(
        public string $method = 'GET',
        public array|Headers $headers = [],
        public mixed $body = null,
        public ?string $mode = null,
        public ?string $credentials = null,
        public ?string $cache = null,
        public ?string $redirect = 'follow',
        public ?string $referrer = null,
        public ?string $referrerPolicy = null,
        public ?string $integrity = null,
        public ?int $timeout = 5,
        public bool $keepalive = false,
    ) {
        $this->method = strtoupper($this->method);

        if (!($this->headers instanceof Headers)) {
            $this->headers = new Headers($this->headers);
        }

        $this->normalize();
    }

    private function normalize(): void
    {
        if (in_array($this->method, ['GET', 'HEAD'])) {
            $this->body = null;
        }

        if (is_array($this->body) || is_object($this->body)) {
            if (!$this->headers->has('content-type')) {
                $this->headers->set('content-type', 'application/json');
            }
            $this->body = json_encode($this->body);
        }
        if (is_string($this->body) && !$this->headers->has('content-length')) {
            $this->headers->set('content-length', strlen($this->body));
        }
    }
}