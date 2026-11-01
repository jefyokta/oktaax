<?php

use Illuminate\Database\Connection;
use Oktaax\Application;
use Oktaax\Database\DB;
use Oktaax\Http\Request;
use OktaaxExamples\MySql\ConnectionPool;
use OktaaxExamples\MySql\MySqlDatabase;
use function OktaaxExamples\Database\registerNoteRoutes;

require_once __DIR__ . '/database/routes.php';
require_once __DIR__ . '/mysql/ConnectionPool.php';
require_once __DIR__ . '/mysql/MySqlDatabase.php';

return function (array $config = []): Application {
    $database = MySqlDatabase::fromEnvironment();
    $app = new Application($config);
    // Lazy worker scope: neither the channel nor a PDO connection is created before fork.
    $app->container()->worker(
        ConnectionPool::class,
        fn() => new ConnectionPool(
            $database->connect(...),
            $database->poolSize,
            $database->poolTimeout,
        ),
        fn(ConnectionPool $pool) => $pool->close(),
    );
    DB::register($app, static function (Request $request) use (
        $app,
    ): Connection {
        /** @var ConnectionPool $pool */
        $pool = $app->container(ConnectionPool::class);
        return $pool->forRequest($request);
    });
    registerNoteRoutes($app, 'MySQL with a worker connection pool');
    return $app;
};
