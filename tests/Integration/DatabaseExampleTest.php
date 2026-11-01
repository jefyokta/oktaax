<?php

use OktaaxExamples\Database\SqliteDatabase;
use Tests\Support\RuntimeServer;

require_once dirname(__DIR__, 2) .
    '/examples/guide/database/SqliteDatabase.php';

it('persists validated notes through SQLite in both runtimes', function (
    string $runtime,
) {
    $path = tempnam(sys_get_temp_dir(), 'oktaax-sqlite-');
    $previous = getenv('OKTAAX_SQLITE_PATH');
    putenv('OKTAAX_SQLITE_PATH=' . $path);
    $server = new RuntimeServer($runtime, 'database');

    try {
        $database = SqliteDatabase::fromEnvironment();
        $database->initialize();
        $server->start();
        expect(json_decode($server->request('/notes')[2], true))->toBe([
            'data' => [],
        ]);
        expect(
            $server->request('/notes', 'POST', '{}', [
                'Content-Type: application/json',
            ])[0],
        )->toBe(422);

        $title = "A note with 'quotes' and SQL: DROP TABLE notes;";
        [$status, $headers, $body] = $server->request(
            '/notes',
            'POST',
            json_encode([
                'title' => $title,
                'body' => 'Original body',
                'id' => 99,
                'ignored' => 'not persisted',
            ]),
            ['Content-Type: application/json'],
        );
        $note = json_decode($body, true)['data'];
        expect($status)
            ->toBe(201)
            ->and($headers['location'])
            ->toBe('/notes/1')
            ->and($note['id'])
            ->toBe(1)
            ->and($note['title'])
            ->toBe($title)
            ->and($note)
            ->not->toHaveKey('ignored');
        expect(
            json_decode($server->request('/notes/1')[2], true)['data'],
        )->toEqual($note);

        expect(
            $server->request(
                '/notes/999',
                'PUT',
                '{"title":"Missing","body":"Missing"}',
                ['Content-Type: application/json'],
            )[0],
        )->toBe(404);
        [$status, , $body] = $server->request(
            '/notes/1',
            'PUT',
            '{"title":"Updated","body":"Saved"}',
            ['Content-Type: application/json'],
        );
        expect($status)
            ->toBe(200)
            ->and(json_decode($body, true)['data']['title'])
            ->toBe('Updated');
        expect(
            $server->request('/notes/1', 'PUT', '{"title":"Incomplete"}', [
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
        $server = new RuntimeServer($runtime, 'database');
        $server->start();
        expect(
            json_decode($server->request('/notes')[2], true)['data'][0]['body'],
        )->toBe('Saved');
        [$status, , $body] = $server->request('/notes/1', 'DELETE');
        expect($status)->toBe(204)->and($body)->toBe('');
        expect($server->request('/notes/1')[0])->toBe(404);
        expect($server->request('/notes/1', 'DELETE')[0])->toBe(404);
        expect(json_decode($server->request('/notes')[2], true))->toBe([
            'data' => [],
        ]);
    } finally {
        $server->stop();
        putenv(
            $previous === false
                ? 'OKTAAX_SQLITE_PATH'
                : 'OKTAAX_SQLITE_PATH=' . $previous,
        );
        foreach ([$path, $path . '-wal', $path . '-shm'] as $file) {
            if (is_file($file)) {
                unlink($file);
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
        getenv('OKTAAX_INTEGRATION') !== '1' ||
            !class_exists(\Illuminate\Database\Capsule\Manager::class) ||
            !extension_loaded('pdo_sqlite'),
        'Requires OKTAAX_INTEGRATION=1, illuminate/database, and pdo_sqlite.',
    );
