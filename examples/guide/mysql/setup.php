<?php

require dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once __DIR__ . '/MySqlDatabase.php';

use OktaaxExamples\MySql\MySqlDatabase;

MySqlDatabase::fromEnvironment()->initialize();
echo "MySQL notes table is ready.\n";
