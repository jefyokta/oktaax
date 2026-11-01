# Illuminate Database + SQLite

[Example catalog](../../README.md) · [Application factory](../database.php) · [Shared routes](routes.php)

A small notes API using an Eloquent `Note` model, schema builder, and
transactions without a Laravel application. `illuminate/database` is included
in this repository's development dependencies. PHP CLI needs `pdo_sqlite` and
Swoole enabled.

## Start the example

Run these commands from the repository root:

```sh
composer install
php examples/guide/database/setup.php
php examples/guide/serve.php database
```

Setup creates `examples/guide/database/database.sqlite` and the `notes` table.
Running setup again preserves existing notes. The database and its WAL files
are ignored by Git. Stop the server with Ctrl+C.

The default runtime is process; the same factory also works with coroutine:

```sh
OKTAAX_RUNTIME=coroutine php examples/guide/serve.php database
```

To choose another file, use the same absolute path during setup and serving.
Its parent directory must already exist and be writable:

```sh
export OKTAAX_SQLITE_PATH=/absolute/path/to/notes.sqlite
php examples/guide/database/setup.php
php examples/guide/serve.php database
```

## Try the API

```sh
curl http://127.0.0.1:3000/notes

curl -i -X POST http://127.0.0.1:3000/notes \
  -H 'Content-Type: application/json' \
  -d '{"title":"First note","body":"Hello from SQLite"}'

curl http://127.0.0.1:3000/notes/1

curl -X PUT http://127.0.0.1:3000/notes/1 \
  -H 'Content-Type: application/json' \
  -d '{"title":"Updated note","body":"Saved with a transaction"}'

curl -i -X DELETE http://127.0.0.1:3000/notes/1
```

On a fresh database, the created note has ID `1`. POST returns HTTP 201, a
`Location: /notes/1` header, and the saved row under `data`. PUT replaces both
`title` and `body`; both fields are required strings for POST and PUT.
Unknown input fields are discarded by validation. Invalid input returns 422,
missing IDs return 404, and successful deletion returns 204 with an empty body.
The list returns the latest 50 notes. Eloquent maintains `created_at` and
`updated_at` automatically and serializes dates to UTC JSON timestamps.

Try a validation failure:

```sh
curl -i -X POST http://127.0.0.1:3000/notes \
  -H 'Content-Type: application/json' -d '{}'
```

Restarting the server retains saved notes. Setup is a minimal, repeatable demo
initializer; use versioned migrations when evolving a real application's schema.

## Eloquent model

[Note.php](Note.php) extends `Oktaax\Database\Eloquent\Model`, defines the `notes` table,
allows mass assignment of `title` and `body`, and casts `id` to an integer.
The routes use model operations:

```php
use Oktaax\Database\DB;

$notes = Note::query()->orderByDesc('id')->limit(50)->get();
$note = DB::transaction(fn() => Note::create($data));
$note = Note::findOrFail($id);
$note->fill($data)->save();
$note->delete();
```

Each operation above illustrates a separate CRUD handler. The recipe wraps
create and update operations in `DB::transaction(...)`. Validation
filters input fields, and the model's `$fillable` also restricts mass assignment.

`DB::register($app, $database->forRequest(...))` configures the request connection
once at bootstrap. The base model resolves that connection from Oktaax's active
application and coroutine context. Queries and transactions in the same request
reuse it. This recipe does not install an Eloquent event dispatcher.

The example catches `ModelNotFoundException` from `findOrFail()` and delegates
to the application's HTTP 404 handler. The default response is the existing HTML
error page; custom application HTTP handlers remain in control of their output.
See the [database integration guide](../../../docs/database.md).

## Connection ownership

`SqliteDatabase` is an application singleton containing only the database path.
It opens no PDO connection before worker processes fork. The registered provider
calls `forRequest($request)` lazily on the first query, and `request->defer()` closes
it when request cleanup runs, including after an exception.

Each connection has its own transaction state. A transaction callback commits
when it returns and rolls back when it throws. Keep the callback limited to
database work; avoid network calls or coroutine sleeps inside a transaction.
Setup enables WAL; connections use a five-second busy timeout. SQLite still
serializes writes, and PDO SQLite queries run synchronously in this example.

The integration uses Capsule instance methods and does not install a global
Capsule or Illuminate's global model resolver. To copy it into your own application, install
`illuminate/database` as a runtime dependency, copy `SqliteDatabase.php`, `Note.php`,
and `routes.php`, and supply your own database path. The package's
[standalone usage guide](https://github.com/illuminate/database#usage-instructions)
also covers the standard global Eloquent setup.

There is no connection pool in this SQLite recipe: each request opens and
releases its connection. A pool can reduce connection setup work, but SQLite
still permits [only one writer at a time](https://www.sqlite.org/isolation.html).
For a MySQL/PostgreSQL version, a
bounded pool should lease connections exclusively, keep transactions on the
same lease, and roll back unfinished transactions before returning a connection.
The [MySQL pool recipe](../mysql/README.md) implements this using the same model
and routes.

## Verify

```sh
OKTAAX_INTEGRATION=1 vendor/bin/pest tests/Integration/DatabaseExampleTest.php
vendor/bin/pest tests/Unit/DatabaseModelTest.php
```

The tests use temporary database files and check CRUD, validation, persistence
after restart, and repeatable setup through both runtimes. The model test checks
request isolation, nested application scopes, transaction rollback, cleanup,
and delegation of missing-model errors to the application's HTTP policy.
