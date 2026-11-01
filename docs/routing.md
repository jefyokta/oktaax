# Routing and middleware

[Guide index](README.md) · [Runnable recipe](../examples/guide/http.php)

## Methods and dynamic paths

`Application` and `Oktaax` provide `get`, `post`, `put`, `patch`, `delete`, `head`,
and `options`. Each accepts a path, handler, and optional route middleware:

```php
use Oktaax\Http\Request;

$app->get(
    '/users/{id:\d+}[/{tab}]',
    fn(Request $request) => [
        'id' => $request->param('id'),
        'tab' => $request->param('tab', 'profile'),
    ],
);
```

Paths follow FastRoute syntax. `/users/42` and `/users/42/history` match; a
nonnumeric ID does not. Query strings do not participate in path matching. Route
parameters are strings. An unknown path is 404. A known path with the wrong
method is 405 and includes `Allow`. HEAD falls back to GET when no explicit HEAD
route matches; response bodies are suppressed.

Do not register the same method/path twice. Register specific paths before broad
patterns where appropriate. Route compilation is cached and invalidated by route
registration. Boot freezes route and middleware registration.

## Handlers

Handlers receive positional arguments `(Request $request, Response $response,
callable $next)`. They may omit trailing arguments. This is not general-purpose
container autowiring: arbitrary typed service parameters are not injected.
Resolve services through your application or capture them in an owning scope.

A handler can be a closure, an invokable object, a callable array, or an invokable
class name with a no-argument constructor. Returning an array sends JSON;
returning a string sends text. See [response values](responses.md).

```php
use Oktaax\Http\Request;

final class ShowUser
{
    public function __invoke(Request $request): array
    {
        return ['id' => $request->param('id')];
    }
}
$app->get('/users/{id:\d+}', ShowUser::class);
```

## Middleware order and short circuits

A callable middleware receives `($request, $response, $next)`. Return `$next()` to
continue, or send/return your own response to stop the chain. Failing to return
`$next()` discards a returned handler value.

```php
use Oktaax\Http\Request;
use Oktaax\Http\Response;

$app->use(function (Request $request, Response $response, callable $next) {
    $response->header('x-request-id', bin2hex(random_bytes(8)));
    return $next();
});
```

`use()` affects routes registered **after** that call. The order is global
middleware, outer group, inner group, then route middleware. Code after `$next()`
runs in reverse order as the calls return. Explicitly repeated middleware is run
for each registration.

A class can implement `Oktaax\Contracts\Middleware` with
`handle(Request $request, Response $response, $next): mixed`. Pass its object or
its no-argument class name. Constructor dependencies require constructing the
object yourself.

## Scoped groups and authentication

`middleware($middleware, $register)` scopes middleware to routes registered in
the callback. It does not add a path prefix. Build the full path explicitly:

```php
use Oktaax\Exception\HttpException;
use Oktaax\Http\Request;

$authenticated = function (Request $request, $response, callable $next) {
    if ($request->header('x-demo-token') !== 'demo') {
        throw new HttpException(401);
    }
    return $next();
};
$app->middleware($authenticated, function ($router) {
    $router->get('/private/profile', fn() => ['authenticated' => true]);
});
```

The fixed token demonstrates the boundary; replace it with your application's
authentication. Group state is restored after registration even if its callback
throws. There is no built-in session store, user model, or JWT login flow.

## CSRF

The current CSRF middleware signs expiring tokens and checks `_token` in the
**body** of non-GET/HEAD/OPTIONS requests. It does not read an `X-CSRF-Token` header
or a query parameter, and it is not a session authentication mechanism.

```php
use Oktaax\Http\Middleware\Csrf;
use Oktaax\Http\Request;

$key = getenv('OKTAAX_APP_KEY'); // Supply a secret of at least 64 bytes for HS512.
$app->use(Csrf::generate($key, 300), Csrf::handle($key));
$app->get('/token', fn(Request $request) => ['token' => $request->_token]);
$app->post(
    '/message',
    fn(Request $request) => $request->validate([
        'message' => 'required|string',
    ]),
);
```

Both middleware must be registered before the protected routes. Missing,
malformed, or expired tokens produce HTTP 403 and stop the chain. The
[CSRF recipe](../examples/guide/csrf.php) includes a development-only fallback key;
its [run instructions](../examples/README.md#csrf) show the full request sequence.
`Oktaax::useCsrf($key, $expiry)` registers the same pair for the fluent entrypoint.

## Inspect a route

```php
[$handler, $params] = $app->router()->resolve('/users/42', 'GET');
```

Use `handleRequest()` when testing complete HTTP behavior. Calling
`router()->dispatch()` alone skips response emission, helper binding, exception
policy, and lifecycle cleanup. Static router accessors no longer exist.
