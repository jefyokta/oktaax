<?php

namespace OktaaxExamples\MySql;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use PDO;

/** Configuration only; connections and the pool are created after workers start. */
final class MySqlDatabase
{
    /** @param array<string, mixed> $configuration */
    private function __construct(
        private array $configuration,
        public readonly int $poolSize,
        public readonly float $poolTimeout,
    ) {}

    public static function fromEnvironment(): self
    {
        if (!class_exists(Capsule::class) || !extension_loaded('pdo_mysql')) {
            throw new \RuntimeException(
                'The MySQL example requires composer install and pdo_mysql.',
            );
        }
        $integer = static function (string $name, int $default, int $max): int {
            $value = getenv($name);
            $result = filter_var(
                $value === false ? $default : $value,
                FILTER_VALIDATE_INT,
                [
                    'options' => ['min_range' => 1, 'max_range' => $max],
                ],
            );
            if ($result === false) {
                throw new \InvalidArgumentException(
                    "{$name} must be between 1 and {$max}.",
                );
            }
            return $result;
        };
        $timeout = filter_var(
            getenv('DB_POOL_TIMEOUT') === false
                ? '1'
                : getenv('DB_POOL_TIMEOUT'),
            FILTER_VALIDATE_FLOAT,
        );
        if ($timeout === false || !is_finite($timeout) || $timeout <= 0) {
            throw new \InvalidArgumentException(
                'DB_POOL_TIMEOUT must be positive seconds.',
            );
        }
        return new self(
            [
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'port' => $integer('DB_PORT', 3306, 65535),
                'database' => getenv('DB_DATABASE') ?: 'oktaax_example',
                'username' => getenv('DB_USERNAME') ?: 'root',
                'password' =>
                    getenv('DB_PASSWORD') === false
                        ? ''
                        : getenv('DB_PASSWORD'),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
                'engine' => 'InnoDB',
                'options' => [
                    PDO::ATTR_PERSISTENT => false,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_TIMEOUT => $integer('DB_CONNECT_TIMEOUT', 5, 60),
                ],
            ],
            $integer('DB_POOL_SIZE', 4, 64),
            $timeout,
        );
    }

    /** A new Capsule manager prevents connection caching across pool slots. */
    public function connect(): Connection
    {
        $capsule = new Capsule();
        $capsule->addConnection($this->configuration);
        return $capsule->getConnection();
    }

    /** Run before serving; the configured database must already exist. Existing notes are preserved. */
    public function initialize(): void
    {
        $connection = $this->connect();
        try {
            $schema = $connection->getSchemaBuilder();
            if (!$schema->hasTable('notes')) {
                $schema->create('notes', static function (
                    Blueprint $table,
                ): void {
                    $table->engine = 'InnoDB';
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
