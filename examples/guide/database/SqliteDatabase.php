<?php

namespace OktaaxExamples\Database;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Oktaax\Http\Request;

/** Configuration can be shared; each request owns its connection. */
final class SqliteDatabase
{
    public function __construct(public readonly string $path) {}

    public static function fromEnvironment(): self
    {
        if (!class_exists(Capsule::class)) {
            throw new \RuntimeException(
                'Run composer install to install the database example dependency.',
            );
        }
        if (!extension_loaded('pdo_sqlite')) {
            throw new \RuntimeException(
                'The database example requires pdo_sqlite.',
            );
        }
        $path = getenv('OKTAAX_SQLITE_PATH') ?: __DIR__ . '/database.sqlite';
        if (!str_starts_with($path, '/')) {
            throw new \InvalidArgumentException(
                'OKTAAX_SQLITE_PATH must be an absolute path.',
            );
        }
        return new self($path);
    }

    /** Open a fresh connection without setting a global Capsule or Eloquent resolver. */
    public function connect(): Connection
    {
        $capsule = new Capsule();
        $capsule->addConnection([
            'driver' => 'sqlite',
            'database' => $this->path,
            'prefix' => '',
            'foreign_key_constraints' => true,
            'busy_timeout' => 5000,
        ]);
        return $capsule->getConnection();
    }

    /** Release the connection after response emission, including failed requests. */
    public function forRequest(Request $request): Connection
    {
        $connection = $this->connect();
        $request->defer(static fn() => $connection->disconnect());
        return $connection;
    }

    /** Run once before starting the server; repeated setup preserves existing notes. */
    public function initialize(): void
    {
        $file = fopen($this->path, 'c');
        if ($file === false) {
            throw new \RuntimeException(
                'Could not create the SQLite database file.',
            );
        }
        fclose($file);

        $connection = $this->connect();
        try {
            $connection->statement('PRAGMA journal_mode = WAL');
            $schema = $connection->getSchemaBuilder();
            if (!$schema->hasTable('notes')) {
                $schema->create('notes', static function (
                    Blueprint $table,
                ): void {
                    $table->increments('id');
                    $table->text('title');
                    $table->text('body');
                    $table->timestamps();
                });
            }
        } finally {
            $connection->disconnect();
        }
    }
}
