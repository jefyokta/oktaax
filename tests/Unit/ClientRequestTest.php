<?php

use Oktaax\HttpClient\Request;
use Oktaax\HttpClient\RequestOptions;

it(
    'serializes HTTP request headers and JSON body with correct framing',
    function () {
        $options = new RequestOptions(
            method: 'POST',
            headers: ['X-Token' => 'abc'],
            body: ['id' => 1],
        );
        $raw = (string) new Request(
            '/users?active=1',
            'example.test',
            $options,
        );
        [$head, $body] = explode("\r\n\r\n", $raw, 2);

        expect($head)->toStartWith("POST /users?active=1 HTTP/1.1\r\n");
        expect($head)->toContain(
            'host: example.test',
            'x-token: abc',
            'content-type: application/json',
            'content-length: 8',
        );
        expect($body)->toBe('{"id":1}');
    },
);

it(
    'does not mutate reusable request options while setting the host',
    function () {
        $options = new RequestOptions(headers: ['x-token' => 'abc']);
        $first = new Request('/', 'first.test', $options);
        $second = new Request('/', 'second.test', $options);

        expect((string) $first)->toContain('host: first.test');
        expect((string) $second)->toContain('host: second.test');
        expect($options->headers->has('host'))->toBeFalse();
    },
);
