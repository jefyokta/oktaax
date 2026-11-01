<?php

use Illuminate\Database\Connection;
use Illuminate\Database\SQLiteConnection;
use Oktaax\Exception\HttpException;
use OktaaxExamples\MySql\ConnectionPool;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Tests\Support\Coroutines;
use Tests\Support\HttpFixture;

require_once dirname(__DIR__, 2) . '/examples/guide/mysql/ConnectionPool.php';

beforeEach(function () {
    if (!class_exists(Connection::class) || !extension_loaded('pdo_sqlite')) {
        $this->markTestSkipped(
            'Pool unit tests require illuminate/database and pdo_sqlite.',
        );
    }
});

it(
    'bounds lazy connection creation and leases each connection exclusively',
    function () {
        Coroutines::run(function () {
            $created = $active = $peak = 0;
            $leased = [];
            $pool = new ConnectionPool(
                function () use (&$created): Connection {
                    $created++;
                    Coroutine::sleep(0.005);
                    return new SQLiteConnection(new PDO('sqlite::memory:'));
                },
                2,
                0.5,
            );
            $done = new Channel(8);
            try {
                expect($created)->toBe(0);
                for ($i = 0; $i < 8; $i++) {
                    Coroutine::create(function () use (
                        $pool,
                        $done,
                        &$active,
                        &$peak,
                        &$leased,
                    ) {
                        $connection = null;
                        $result = true;
                        try {
                            $connection = $pool->acquire();
                            $key = spl_object_id($connection);
                            if (isset($leased[$key])) {
                                throw new RuntimeException(
                                    'A connection was leased twice.',
                                );
                            }
                            $leased[$key] = true;
                            $active++;
                            $peak = max($peak, $active);
                            Coroutine::sleep(0.005);
                            $active--;
                            unset($leased[$key]);
                        } catch (Throwable $error) {
                            $result = $error;
                        } finally {
                            if ($connection !== null) {
                                $pool->release($connection);
                            }
                            $done->push($result);
                        }
                    });
                }
                for ($i = 0; $i < 8; $i++) {
                    $result = $done->pop(1);
                    if ($result instanceof Throwable) {
                        throw $result;
                    }
                    expect($result)->toBeTrue();
                }
                expect($created)->toBe(2)->and($peak)->toBe(2);
            } finally {
                $pool->close();
            }
        });
    },
);

it(
    'times out a saturated pool and rolls back before a request lease is reused',
    function () {
        Coroutines::run(function () {
            $pool = new ConnectionPool(
                fn() => new SQLiteConnection(new PDO('sqlite::memory:')),
                1,
                0.01,
            );
            try {
                $http = new HttpFixture();
                $connection = $pool->forRequest($http->request);
                $connection->statement('CREATE TABLE records (value TEXT)');
                $connection->beginTransaction();
                $connection->insert('INSERT INTO records VALUES (?)', [
                    'uncommitted',
                ]);
                try {
                    $pool->acquire();
                    throw new RuntimeException(
                        'Expected the pool to time out.',
                    );
                } catch (HttpException $error) {
                    expect($error->getStatusCode())
                        ->toBe(503)
                        ->and($error->getHeaders())
                        ->toBe(['Retry-After' => '1']);
                }
                $http->request->scope()->close();
                $next = $pool->acquire();
                expect($next)
                    ->toBe($connection)
                    ->and($next->transactionLevel())
                    ->toBe(0)
                    ->and($next->scalar('SELECT COUNT(*) FROM records'))
                    ->toBe(0);
                $pool->release($next);
                $pool->release($next);
                $again = $pool->acquire();
                expect(fn() => $pool->acquire())->toThrow(HttpException::class);
                $pool->release($again);
            } finally {
                $pool->close();
            }
        });
    },
);

it('restores a slot after a connection factory fails', function () {
    Coroutines::run(function () {
        $attempts = 0;
        $pool = new ConnectionPool(
            function () use (&$attempts): Connection {
                if (++$attempts === 1) {
                    throw new RuntimeException('Connection failed.');
                }
                return new SQLiteConnection(new PDO('sqlite::memory:'));
            },
            1,
            0.01,
        );
        try {
            expect(fn() => $pool->acquire())->toThrow(
                RuntimeException::class,
                'Connection failed.',
            );
            $connection = $pool->acquire();
            expect($attempts)->toBe(2);
            $pool->release($connection);
        } finally {
            $pool->close();
        }
    });
});

it('discards a connection when transaction cleanup fails', function () {
    Coroutines::run(function () {
        $broken = Mockery::mock(Connection::class);
        $broken
            ->shouldReceive('selectOne')
            ->with('SELECT 1')
            ->once()
            ->andReturn((object) ['1' => 1]);
        $broken->shouldReceive('transactionLevel')->once()->andReturn(1);
        $broken
            ->shouldReceive('rollBack')
            ->with(0)
            ->once()
            ->andThrow(new RuntimeException('Lost connection.'));
        $broken->shouldReceive('disconnect')->once();
        $created = 0;
        $pool = new ConnectionPool(
            function () use (&$created, $broken): Connection {
                return ++$created === 1
                    ? $broken
                    : new SQLiteConnection(new PDO('sqlite::memory:'));
            },
            1,
            0.01,
        );
        try {
            $pool->release($pool->acquire());
            $replacement = $pool->acquire();
            expect($replacement)->not->toBe($broken)->and($created)->toBe(2);
            $pool->release($replacement);
        } finally {
            $pool->close();
        }
    });
});

it(
    'wakes waiting borrowers on shutdown and closes active connections on return',
    function () {
        Coroutines::run(function () {
            $pool = new ConnectionPool(
                fn() => new SQLiteConnection(new PDO('sqlite::memory:')),
                1,
                0.5,
            );
            $connection = $pool->acquire();
            $done = new Channel(1);
            Coroutine::create(function () use ($pool, $done) {
                try {
                    $pool->acquire();
                    $done->push(false);
                } catch (Throwable $error) {
                    $done->push($error);
                }
            });
            Coroutine::sleep(0.005);
            $pool->close();
            $error = $done->pop(0.1);
            expect($error)->toBeInstanceOf(HttpException::class);
            expect($connection->scalar('SELECT 1'))->toBe(1);
            $pool->release($connection);
            expect($connection->getRawPdo())->toBeNull();
            expect(fn() => $pool->acquire())->toThrow(HttpException::class);
            $pool->close();
        });
    },
);
