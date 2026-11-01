<?php

namespace Tests\Support;

use Oktaax\Http\Request;
use Oktaax\Http\Response;

final class HttpFixture
{
    public NativeRequest $nativeRequest;
    public NativeResponse $nativeResponse;
    public Request $request;
    public Response $response;

    public function __construct(
        string $method = 'GET',
        string $uri = '/',
        string $body = '',
        array $headers = [],
        array $query = [],
        array $post = [],
        array $cookies = [],
    ) {
        $this->nativeRequest = new NativeRequest();
        $this->nativeRequest->raw = $body;
        $this->nativeRequest->server = [
            'request_method' => $method,
            'request_uri' => $uri,
        ];
        $this->nativeRequest->header = $headers;
        $this->nativeRequest->get = $query;
        $this->nativeRequest->post = $post;
        $this->nativeRequest->cookie = $cookies;
        $this->nativeResponse = new NativeResponse();
        $this->request = new Request($this->nativeRequest);
        $this->response = new Response($this->nativeResponse, $this->request);
    }
}
