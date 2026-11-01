# Responses, files, and views

[Guide index](README.md) · [Runnable recipe](../examples/guide/responses.php)

## Return a value or send a response

| Handler result                   | Emission                                                    |
| -------------------------------- | ----------------------------------------------------------- |
| `string`                         | Response body                                               |
| `array` or `Traversable`         | JSON; traversables are materialized                         |
| `null`                           | End response; `x-no-content: 1` if headers have not started |
| `Generator`                      | Write each yielded scalar/stringable chunk, then end        |
| `Promise`                        | Await, then emit the resolved value                         |
| `Http\Response\JsonEnvelope`     | Explicit JSON envelope                                      |
| `Http\Response\File`             | Native file send                                            |
| `Http\Response\StreamedResponse` | Run callback with a write function, then end                |
| `Http\Response\SseResponse`      | Bind/start a detached SSE connection                        |
| `Contracts\Jsonable`             | Serialize the value from `toJson()` as JSON                 |
| Registered custom result type    | Run its response handler                                    |

All namespaces in the table are relative to `Oktaax`. Booleans, integers, and
arbitrary objects are not automatically serialized. Wrap them in an array or
register a custom result handler. A plain string does not set a Content-Type;
set it explicitly when needed.

You can also call `$response->end()`, `json()`, or `render()` in the handler. Once
the response has ended, the emitter does not send a second body. A custom result
handler owns sending its response; returning another value from that handler does
not cause recursive emission.

## JSON and envelopes

```php
$app->post('/users', function ($request, \Oktaax\Http\Response $response) {
    $data = $request->validate(['name' => 'required|string']);
    $response->status(201)->json($data);
});
```

`json()` serializes exactly its argument with `JSON_THROW_ON_ERROR`. Field names
such as `message` have no special meaning. For an envelope:

```php
$response->jsonEnvelope(['id' => 1], 'Created');
// {"message":"Created","error":[],"data":{"id":1}}
```

Or return `new \Oktaax\Http\Response\JsonEnvelope(data: ['id' => 1], message: 'Created')`.
Encoding invalid values raises `JsonException`.

## Status, headers, and cookies

```php
$response
    ->status(202)
    ->header('x-job-state', 'accepted')
    ->json(['accepted' => true]);
$response->cookie(
    'preference',
    'compact',
    time() + 3600,
    httponly: true,
    samesite: 'Lax',
);
```

Set status, headers, and cookies before sending any body bytes. `header()`
normalizes names. Calling it or `status()` after headers are committed throws
`LogicException`. `sendHeaders()`, the first write, end, or file send commits
status and headers once. `type('json')` resolves a MIME extension; a complete
media type such as `type('text/plain; charset=utf-8')` also works.

`cookie()` supports name, value, expiry, path, domain, secure, httponly, samesite,
and priority. Set `secure: true` when your cookie should travel only over HTTPS.
`getStatus()` and `getHeaders()` inspect the response wrapper's state.
`getContent()` reports the body passed to `end()`; it is not a complete transcript
of streamed writes.

## Redirects and downloads

```php
$response->redirect('/dashboard'); // 302; pass a status as the second argument.
```

Use destinations you control. `back($default)` uses the client's Referer header;
it is not a trusted destination resolver.

```php
use Oktaax\Http\Response\File;

$app->get(
    '/download',
    fn() => new File(__DIR__ . '/files/report.txt', 200, [
        'content-type' => 'text/plain; charset=utf-8',
        'content-disposition' => 'attachment; filename="report.txt"',
    ]),
);
```

`$response->sendfile($path, $offset = 0, $length = null)` is the imperative form.
Resolve file paths from an application-owned directory, not raw user input.
HEAD commits the same response metadata without sending file bytes.

## PHP templates

The default engine is `Oktaax\View\PhpView`, initially rooted at `views/` relative
to the working directory. Prefer an absolute path:

```php
use Oktaax\View\PhpView;

$app->setView(new PhpView(__DIR__ . '/views'));
$app->get(
    '/',
    fn($request, $response) => $response->type('html')->render('welcome', [
        'name' => $request->query('name', 'friend'),
    ]),
);
```

`render('welcome')` loads `views/welcome.php`; data keys become template variables.
Escape untrusted output in your template with
`htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`.
View names are application-controlled. Missing templates throw `ViewNotFound`.
To replace the engine, implement `Contracts\View::render(string $view, array
$data): ?string` and register it using `setView()` before boot.

## Custom result types and helpers

```php
final class AcceptedJob
{
    public function __construct(public string $id) {}
}

$app->respond(AcceptedJob::class, function (
    AcceptedJob $job,
    $request,
    $response,
) {
    $response->status(202)->json(['job_id' => $job->id]);
});
$app->get('/job', fn() => new AcceptedJob('demo-1'));
```

Register at bootstrap. Exact types are checked before compatible registered
parent/interface types. Built-in string/array/generator emission runs before
custom object handlers.

`inject(Response::class, 'methodName', $callable)` adds an application-owned helper
before boot. Helpers receive their supplied method arguments; they are not
implicitly bound to `$this`. Pass needed data explicitly, or use scoped `app()`
inside a request. A missing helper raises `BadMethodCallException`.

## Inertia page payloads

The [Inertia recipe](../examples/guide/inertia.php) shows the existing server
payload integration. Configure `inertia.base_view` and a PHP view before boot:

```php
$app->config()->set('inertia.base_view', 'inertia');
$app->get('/', function ($request, $response) {
    if (!$request->isInertia()) {
        $response->type('html');
    }
    \Oktaax\Http\Inertia::render('Welcome', ['name' => 'Ada']);
});
```

For a request with `X-Inertia`, it sends JSON containing `component`, `props`,
`url`, and `version` (currently null), plus an `X-Inertia` header. Otherwise it
renders the base view with a `$page` variable. Put escaped JSON in the root
`data-page` attribute and mount your own frontend there. This helper does not
provide frontend assets, version negotiation, partial reloads, or a complete
Inertia protocol adapter.

Continue with [streaming](streaming.md) and [error handling](errors.md).
