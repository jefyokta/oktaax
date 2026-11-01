# Errors and debug pages

[Guide index](README.md) · [Error page gallery](../examples/guide/errors.php)

## Default policy

| Failure                        | Default response                             |
| ------------------------------ | -------------------------------------------- |
| Unknown route                  | HTML 404                                     |
| Method mismatch                | HTML 405 with `Allow`                        |
| `HttpException`                | HTML using its status and response headers   |
| Invalid JSON                   | HTML 400                                     |
| `ValidationException`          | JSON 422 with `errors`                       |
| Unhandled throwable, debug off | Generic HTML 500; details logged server-side |
| Unhandled throwable, debug on  | HTML 500 exception diagnostics               |
| Client already closed          | No additional body                           |

The HTML pages are self-contained: system fonts, inline CSS and SVG, no external
assets, a mobile layout, readable request context, and a home link. HTTP pages
have a status-specific explanation. Unknown status titles fall back to
`Request Error`.

Once a response has started, the default exception policy ends it instead of
trying to change committed status/headers or append a full error document.
A streamed response can therefore retain its earlier status if its producer
fails after the first write.

## Preview the pages

```sh
OKTAAX_DEBUG=1 php examples/guide/serve.php errors
```

Open `http://127.0.0.1:3000/` for the gallery, or visit `/http/404`, `/http/403`,
`/http/429`, `/http/503`, and `/exception` directly. Stop the server and run with
`OKTAAX_DEBUG=0` to compare production behavior. Debug is disabled by default.

In your own application:

```php
$app = new \Oktaax\Application(['app' => ['debug' => true]]);
```

Debug pages include the escaped exception class/message, source location, seven
nearby source lines where available, and expandable stack frames. They omit
request bodies, headers, query strings, environment dumps, and trace argument
values. Source code and exception messages can still contain application secrets;
keep debug disabled for public traffic. `Logger` middleware delegates exceptions
to this same kernel policy instead of rendering its own page.

## Raise an HTTP error

```php
throw new \Oktaax\Exception\HttpException(403, 'Access denied');
throw new \Oktaax\Exception\HttpException(405, headers: ['Allow' => 'POST']);
```

The default public HTML uses the standard status explanation rather than exposing
the supplied exception message. `$response->renderHttpError(404)` explicitly
renders an HTTP page. `$response->renderException($error)` renders a debug page
only if debug was bound by the kernel; otherwise it renders generic 500.
Both set `Content-Type: text/html; charset=utf-8` and the appropriate status.

## Custom JSON error policy

Register handlers before run, or during worker bootstrap:

```php
use Oktaax\Exception\HttpException;

$app->catch(HttpException::class, function (
    HttpException $error,
    $request,
    $response,
) {
    if (!$response->isWritable()) {
        return;
    }
    if ($response->hasStarted()) {
        $response->end();
        return;
    }
    foreach ($error->getHeaders() as $name => $value) {
        $response->header($name, $value);
    }
    $response
        ->status($error->getStatusCode())
        ->json(['error' => 'Request failed']);
});
```

Preserve HTTP headers such as `Allow` and `Retry-After` when replacing the default
handler. Register domain-specific classes for precise API errors; see the gallery
recipe's `/api/conflict`.

Exception dispatch checks the concrete class and its parents before registered
compatible interfaces. A handler for `Throwable` alone does not override an
existing more-specific HTTP or validation handler. Replace those classes
explicitly when you need a fully JSON API. Handler return values are ignored;
the handler must send its response. Log internal details separately from the
public response.

The [Eloquent example](database.md#handle-missing-models) catches
`ModelNotFoundException` and delegates to this same application's HTTP 404
policy, preserving custom HTTP handlers.

## Replace the HTML templates

Set absolute, readable PHP file paths before boot:

```php
$app->config()->set('app.error_views.http', __DIR__ . '/views/http-error.php');
$app->config()->set(
    'app.error_views.exception',
    __DIR__ . '/views/exception.php',
);
```

The application lazily constructs `Http\ErrorPageRenderer` with those paths.
Omitted paths use the built-ins. This mechanism is independent from the regular
`PhpView` directory and `setView()`.

Both templates receive `$status`, `$title`, `$requestMethod`, `$requestPath`, and
an `$escape($value)` closure. HTTP templates additionally receive `$description`.
Debug exception templates receive `$error`, `$exceptionClass`, `$message`,
`$file`, `$line`, `$source` (line number to raw source text), and `$frames`
(file, line, call; no arguments). Exception overrides are only used in debug.

A minimal custom HTTP template:

```php
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title><?= $escape($status) ?> · <?= $escape($title) ?></title></head>
<body>
    <h1><?= $escape($status) ?> — <?= $escape($title) ?></h1>
    <p><?= $escape($description) ?></p>
    <a href="/">Back to home</a>
</body>
</html>
```

Custom templates own their markup and escaping. The renderer restores its output
buffer even if a template throws. Keep templates simple and test them: a failing
error template can itself prevent an error response from completing.
