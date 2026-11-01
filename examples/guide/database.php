<?php

use Oktaax\Application;
use Oktaax\Database\DB;
use OktaaxExamples\Database\SqliteDatabase;
use function OktaaxExamples\Database\registerNoteRoutes;

require_once __DIR__ . '/database/SqliteDatabase.php';
require_once __DIR__ . '/database/routes.php';

return function (array $config = []): Application {
    $database = SqliteDatabase::fromEnvironment();
    if (!is_file($database->path)) {
        throw new RuntimeException(
            'Run php examples/guide/database/setup.php before starting the database example.',
        );
    }

    $app = new Application($config);
    // This singleton contains configuration only, with no PDO opened before workers fork.
    $app->container()->singleton(SqliteDatabase::class, $database);

    DB::register($app, $database->forRequest(...));
    registerNoteRoutes($app, 'SQLite');
    return $app;
};
