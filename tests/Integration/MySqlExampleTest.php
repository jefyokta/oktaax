<?php

use OktaaxExamples\MySql\ConnectionPool;
use OktaaxExamples\MySql\MySqlDatabase;
use Tests\Support\Coroutines;
use Tests\Support\RuntimeServer;

require_once dirname(__DIR__, 2) . '/examples/guide/mysql/MySqlDatabase.php';
require_once dirname(__DIR__, 2) . '/examples/guide/mysql/ConnectionPool.php';

it('serves Eloquent CRUD through a MySQL worker pool', function (
    string $runtime,
) {
    $database = MySqlDatabase::fromEnvironment();
    $database->initialize();
    $server = new RuntimeServer($runtime, 'mysql');
    $id = null;
    try {
        $server->start();
        $title = 'MySQL example ' . bin2hex(random_bytes(8));
        [$status, $headers, $body] = $server->request(
            '/notes',
            'POST',
            json_encode([
                'title' => $title,
                'body' => 'Persisted by Eloquent',
                'ignored' => 'not stored',
            ]),
            ['Content-Type: application/json'],
        );
        expect($status)->toBe(201);
        $note = json_decode($body, true)['data'];
        $id = $note['id'];
        expect($headers['location'])
            ->toBe('/notes/' . $id)
            ->and($note['title'])
            ->toBe($title)
            ->and($note)
            ->toHaveKeys(['created_at', 'updated_at'])
            ->and($note)
            ->not->toHaveKey('ignored');
        expect(
            json_decode($server->request('/notes/' . $id)[2], true)['data'],
        )->toEqual($note);
        expect(
            $server->request('/notes', 'POST', '{}', [
                'Content-Type: application/json',
            ])[0],
        )->toBe(422);
        [$status, , $body] = $server->request(
            '/notes/' . $id,
            'PUT',
            '{"title":"Updated","body":"New content"}',
            ['Content-Type: application/json'],
        );
        expect($status)
            ->toBe(200)
            ->and(json_decode($body, true)['data']['body'])
            ->toBe('New content');
        expect(
            $server->request('/notes/' . $id, 'PUT', '{"title":"Incomplete"}', [
                'Content-Type: application/json',
            ])[0],
        )->toBe(422);

        $server->stop();
        expect($server->gracefulShutdown)->toBeTrue();
        expect($server->logs())->not->toContain(
            'Fatal error',
            'PHP Warning',
            'PHP Deprecated',
        );
        $database->initialize();
        $server = new RuntimeServer($runtime, 'mysql');
        $server->start();
        expect(
            json_decode($server->request('/notes/' . $id)[2], true)['data'][
                'body'
            ],
        )->toBe('New content');
        [$status, , $body] = $server->request('/notes/' . $id, 'DELETE');
        expect($status)->toBe(204)->and($body)->toBe('');
        expect($server->request('/notes/' . $id)[0])->toBe(404);
        expect($server->request('/notes/' . $id, 'DELETE')[0])->toBe(404);
    } finally {
        $server->stop();
        if ($id !== null) {
            $connection = $database->connect();
            try {
                $connection->table('notes')->where('id', $id)->delete();
            } finally {
                $connection->disconnect();
            }
        }
    }
    expect($server->gracefulShutdown)->toBeTrue();
    expect($server->logs())->not->toContain(
        'Fatal error',
        'PHP Warning',
        'PHP Deprecated',
    );
})
    ->with(['process', 'coroutine'])
    ->skip(
        getenv('OKTAAX_MYSQL_INTEGRATION') !== '1' ||
            !extension_loaded('pdo_mysql'),
        'Requires OKTAAX_MYSQL_INTEGRATION=1 and the MySQL example DB_* configuration.',
    );

it(
    'reuses a MySQL session only after rolling back its unfinished transaction',
    function () {
        $database = MySqlDatabase::fromEnvironment();
        $database->initialize();
        Coroutines::run(function () use ($database) {
            $pool = new ConnectionPool($database->connect(...), 1, 0.1);
            try {
                $connection = $pool->acquire();
                $session = $connection->scalar('SELECT CONNECTION_ID()');
                $title = 'Uncommitted ' . bin2hex(random_bytes(8));
                $connection->beginTransaction();
                $connection->table('notes')->insert([
                    'title' => $title,
                    'body' => 'Must be rolled back',
                ]);
                $pool->release($connection);

                $next = $pool->acquire();
                expect($next->scalar('SELECT CONNECTION_ID()'))
                    ->toBe($session)
                    ->and($next->transactionLevel())
                    ->toBe(0)
                    ->and(
                        $next->table('notes')->where('title', $title)->exists(),
                    )
                    ->toBeFalse();
                $pool->release($next);
            } finally {
                $pool->close();
            }
        });
    },
)->skip(
    getenv('OKTAAX_MYSQL_INTEGRATION') !== '1' ||
        !extension_loaded('pdo_mysql'),
    'Requires OKTAAX_MYSQL_INTEGRATION=1 and the MySQL example DB_* configuration.',
);
