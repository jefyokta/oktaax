# Eloquent + MySQL connection pool

[Example catalog](../../README.md) · [Application factory](../mysql.php) · [Note model](../database/Note.php)

The notes API uses Eloquent and a bounded pool per worker. PHP CLI needs
`pdo_mysql` and Swoole enabled. Run commands from the repository root.

## Start the example

```sh
composer install
docker compose -f examples/guide/mysql/compose.yaml up -d --wait

export DB_HOST=127.0.0.1
export DB_PORT=3307
export DB_DATABASE=oktaax_example
export DB_USERNAME=oktaax
export DB_PASSWORD=example-secret
export DB_POOL_SIZE=4
export DB_POOL_TIMEOUT=1

php examples/guide/mysql/setup.php
php examples/guide/serve.php mysql
```

Compose credentials are for this local demo. MySQL binds to loopback and stores
data in a named volume. With an existing server, skip Compose and supply its
`DB_*` configuration. The database must already exist; setup creates the `notes`
table and preserves notes on subsequent runs. The setup user needs permission
to create tables. Configuration is read from environment variables, without an
automatic `.env` loader.

Use `OKTAAX_RUNTIME=coroutine php examples/guide/serve.php mysql` to choose the
coroutine runtime. Stop the API with Ctrl+C. Stop MySQL while keeping its data:

```sh
docker compose -f examples/guide/mysql/compose.yaml down
```

## Try CRUD

```sh
curl -i -X POST http://127.0.0.1:3000/notes \
  -H 'Content-Type: application/json' \
  -d '{"title":"MySQL note","body":"Saved with Eloquent"}'
curl http://127.0.0.1:3000/notes
curl http://127.0.0.1:3000/notes/1
curl -X PUT http://127.0.0.1:3000/notes/1 \
  -H 'Content-Type: application/json' \
  -d '{"title":"Updated","body":"Using the worker pool"}'
curl -i -X DELETE http://127.0.0.1:3000/notes/1
```

Use the ID returned by POST; `1` assumes a fresh database. Expected statuses:
201 for creation, 200 for reads and updates, and 204 for deletion. Missing IDs
return 404; invalid or missing `title`/`body` fields return 422. The list contains
the latest 50 notes. Responses contain saved rows under `data`. Eloquent manages
timestamps and restricts mass assignment through `$fillable`.

## Environment variables

| Variable             | Default          | Purpose                                      |
| -------------------- | ---------------- | -------------------------------------------- |
| `DB_HOST`            | `127.0.0.1`      | MySQL hostname or TCP address                |
| `DB_PORT`            | `3307`           | Port matching the Compose service            |
| `DB_DATABASE`        | `oktaax_example` | An existing database                         |
| `DB_USERNAME`        | `oktaax`         | Database user                                |
| `DB_PASSWORD`        | Empty string     | Database password                            |
| `DB_POOL_SIZE`       | `4`              | Maximum connections per worker, from 1 to 64 |
| `DB_POOL_TIMEOUT`    | `1`              | Positive seconds to wait for a pool slot     |
| `DB_CONNECT_TIMEOUT` | `5`              | PDO connection timeout, from 1 to 60 seconds |

The normal [`OKTAAX_*` runner variables](../../../docs/configuration.md#environment-variables-in-the-example-runner)
control HTTP host, port, debug, and runtime. Pool waiting and connection setup
have separate timeouts; neither is a general SQL execution deadline.

## Ownership and cleanup

`ConnectionPool` is a lazy worker service. No channel or PDO connection is
created before fork. A slot is reserved before a yielding connection attempt,
so concurrent attempts cannot exceed capacity. A health query checks each
connection before it reaches a handler.

`DB::register()` installs a provider that calls the pool's `forRequest()` lazily
on the first query. `Note` extends `Oktaax\Database\Eloquent\Model`, so handlers
can use `Note::query()`, `Note::create()`, and `Note::findOrFail()` directly.
Every query and `DB::transaction()` in that request uses the same exclusive
lease until `request->defer()` returns it during cleanup. Serialize models
before cleanup; do not retain models or cursors in worker singletons or use them
after the lease has returned.

The shared example catches `ModelNotFoundException` and delegates to the
application's existing HTTP 404 policy. It does not force JSON or parse headers
to choose an error response. See the [database integration guide](../../../docs/database.md).

Release rolls back unfinished Illuminate or direct PDO transactions and clears
query logging. Failed cleanup discards the connection and frees its slot.
Exhaustion returns HTTP 503 with `Retry-After: 1`. Worker disposal closes idle
connections, wakes waiting borrowers, and closes active connections on return.

The pool resets transaction state, not arbitrary session variables, temporary
tables, or session locks. For work that changes those, acquire explicitly and
discard in `finally`:

```php
$connection = $pool->acquire();
try {
    // Work that changes session state.
} finally {
    $pool->release($connection, discard: true);
}
```

Do not mix manual release with `forRequest()` cleanup. PDO persistent connections
are disabled because the pool owns reuse;
[persistent PDO connections do not clean session state automatically](https://www.php.net/manual/en/pdo.connections.php).

For `N` HTTP workers, allow up to `N × DB_POOL_SIZE` connections, plus CLI/task
clients. Coroutine I/O depends on Swoole's PDO/mysqlnd hook support; the runner
enables runtime hooks, but a pool alone does not make a blocking driver async.

## Verify

Use a dedicated test database with the same `DB_*` configuration:

```sh
OKTAAX_MYSQL_INTEGRATION=1 vendor/bin/pest tests/Integration/MySqlExampleTest.php
vendor/bin/pest tests/Unit/DatabasePoolTest.php
```

MySQL tests check CRUD through both runtimes, persistence across restarts,
physical session reuse, and rollback before reuse. They remove only the notes
they create. Pool unit tests use in-memory SQLite to check exclusive leases,
capacity during concurrent creation, timeouts, failures, and shutdown.
