<?php

require dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once __DIR__ . '/SqliteDatabase.php';

use OktaaxExamples\Database\SqliteDatabase;

$database = SqliteDatabase::fromEnvironment();
$database->initialize();
echo "SQLite database ready: {$database->path}\n";
