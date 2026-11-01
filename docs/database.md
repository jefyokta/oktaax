# Database and Eloquent

[Guide index](README.md) · [SQLite recipe](../examples/guide/database/README.md) · [MySQL pool recipe](../examples/guide/mysql/README.md)

The optional database bridge provides Eloquent's model API with connections
resolved from the active Oktaax HTTP request. Install the database component in
your application:

```sh
composer require 'illuminate/database:^12.0 || ^13.0'
```

The repository includes it as a development dependency for its examples. Use
`pdo_sqlite` for SQLite or `pdo_mysql` for MySQL.

## Register a connection provider

Before application boot, register a factory that accepts the current request
and returns an Illuminate `Connection`. The factory must register cleanup for
the connection it opens or borrows. For example, using the SQLite helper from
the recipe:

```php
use Oktaax\Database\DB;

DB::register($app, $database->forRequest(...));
```

The [MySQL factory](../examples/guide/mysql.php) registers the same bridge with
a worker pool's `forRequest()` method. `DB` calls that provider lazily, once per
request. Every model query and transaction in that request then uses the same
connection. A route that does not query the database acquires no connection.

## Define and use models

Extend the Oktaax base model:

```php
use Oktaax\Database\Eloquent\Model;

final class Note extends Model
{
    protected $table = 'notes';
    protected $fillable = ['title', 'body'];
    protected $casts = ['id' => 'integer'];
}
```

In a request handler, use the standard Eloquent methods:

```php
$notes = Note::query()->orderByDesc('id')->limit(50)->get();
$note = Note::create(['title' => 'First note', 'body' => 'Hello']);
$note = Note::findOrFail($id);
$note->fill(['title' => 'Updated'])->save();
$note->delete();
```

The base model changes connection lookup, while Eloquent retains attribute
handling, mass assignment, timestamps, casts, and its query builder. No global
Capsule or Illuminate model resolver is installed. Other models using the plain
Illuminate base class keep their existing resolver. For relationships, related
application models should also extend the Oktaax base model.

## Transactions

```php
use Oktaax\Database\DB;

$note = DB::transaction(function () use ($data) {
    return Note::create($data);
});

$connection = DB::connection();
$rows = $connection->table('notes')->limit(10)->get();
```

`DB::transaction()` returns the callback result, commits on success, and rolls
back when the callback throws. Model queries inside the callback resolve the
same connection. Nested transactions use Illuminate's transaction management.

## Handle missing models

`findOrFail()` throws Illuminate's `ModelNotFoundException`. The shared example
registers a handler that converts it into HTTP 404 and delegates rendering to
the application's existing HTTP error policy:

```php
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Oktaax\Exception\HttpException;
use Oktaax\Http\Request;
use Oktaax\Http\Response;

$app->catch(ModelNotFoundException::class, static function (
    ModelNotFoundException $error,
    ?Request $request,
    ?Response $response,
) use ($app): void {
    $app->kernel()
        ->exceptions()
        ->dispatch(
            new HttpException(404, 'Note not found.'),
            $request,
            $response,
        );
});
```

The default policy renders the existing HTML 404 page. If your application
registers a custom `HttpException` handler, that handler also receives these
404s. The database example does not force JSON or implement separate response
negotiation. See [application error policies](errors.md#custom-json-error-policy).

## Scope and cleanup

Connection state lives in Oktaax's context, isolated by application and coroutine.
Request cleanup closes a SQLite connection or returns a MySQL lease; the kernel
then clears the request context. No connection is acquired before worker fork.

This bridge currently supports the `default` connection in HTTP request scopes.
Model queries outside an active request, or during request cleanup, throw a
`LogicException` rather than borrowing another request's connection. Schema
setup scripts use an explicit connection, as shown in the recipes. An
`Application::within()` scope alone does not create an HTTP request.

Do not retain models, lazy relationships, query builders, or cursors after
request cleanup. Serialize results during the request and keep transactions
short. The MySQL recipe documents pool limits, timeouts, and session cleanup.
