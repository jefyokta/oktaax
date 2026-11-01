# Outbound HTTP client

[Guide index](README.md) · [Runnable local upstream example](../examples/guide/http-client.php)

`Oktaax\Utils\fetch()` uses a coroutine TCP client and returns the bundled promise
library's `Promise`. Use it in a request handler or an explicit Swoole coroutine.
The global `await()` helper comes from that dependency.

```php
use Oktaax\HttpClient\RequestOptions;
use function Oktaax\Utils\fetch;

$app->get('/proxy', function () {
    $upstream = await(
        fetch('http://127.0.0.1:4000/data', new RequestOptions(timeout: 2)),
    );
    try {
        if (!$upstream->ok) {
            throw new RuntimeException(
                'Upstream returned HTTP ' . $upstream->status,
            );
        }
        $data = await($upstream->json());
        return ['upstream' => $data];
    } finally {
        $upstream->close();
    }
});
```

This snippet needs an upstream at port 4000. The runnable recipe instead calls
its own `/upstream` route and works without an external service.

## Request options

A `RequestOptions` object or array can supply `method`, `headers`, `body`, and
`timeout`. Prefer the object with an explicit timeout in seconds:

```php
$options = new RequestOptions(
    method: 'POST',
    headers: ['accept' => 'application/json'],
    body: ['name' => 'Ada'],
    timeout: 2,
);
```

Methods normalize to uppercase. GET/HEAD discard a supplied body. An array/object
body is JSON-encoded and gets a JSON Content-Type unless already supplied; a
string body is sent as-is. For URL-encoded form data, pass
`http_build_query($fields)` and the matching Content-Type explicitly.

The `RequestOptions` DTO also exposes browser-inspired fields such as `mode`,
`credentials`, `cache`, `redirect`, `referrer`, `integrity`, and `keepalive`.
The current transport does not implement browser cookie storage, CORS, redirects,
caching, or integrity enforcement from those fields. HTTPS selects an SSL socket;
there is no per-request certificate-policy API in this helper.

## Responses and body ownership

- `status`, `ok`, and `ok()` expose status. A non-2xx HTTP response resolves;
  your application decides how to handle it.
- `headers` is an `Oktaax\Http\Headers` collection with `get`, `has`, `entries`,
  iteration, and `getSetCookie` support.
- `text()`, `json()`, `bytes()`, and `formData()` return promises.
- `json()` returns ordinary `json_decode()` values: JSON objects become
  `stdClass`, unlike the server request's associative-array JSON decoding.
- `bytes()` returns a list of byte integers. `formData()` parses supported
  URL-encoded/multipart response bodies; it is not a browser `FormData` object.
- The body is consumed once. Call one reader, not `text()` followed by `json()`.
- Reading the full body closes the connection. Call `close()` if you do not read
  it, and keep a `finally` block for failures.

The transport understands Content-Length, chunked encoding, and close-delimited
bodies. Body readers buffer the complete response in memory. The helper does not
provide a streamed download, automatic decompression, redirect following, retry,
or a connection pool. It is a small HTTP/1.1 client; choose a dedicated client in
your application when you need those features.

`fetch()` rejects transport failures; body readers reject parsing/consumption
failures. Catch errors at your service boundary and map them to your API's chosen
response. Do not treat an upstream's raw exception message as a public response.
