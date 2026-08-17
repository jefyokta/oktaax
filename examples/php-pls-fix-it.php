<?php

declare(strict_types=1);

use Oktaax\Console;

require "vendor/autoload.php";

$null = null;


$null[] = 'it should be a type error';

var_dump($null);
// array(1) {
//   [0]=>
//   string(23) "it should be type error"
// }



$map = new WeakMap;
$object = new stdClass;
$nonWeak = [];
$key = $object::class;

$nonWeak[$key] = 'saoasoas';

$map[$object] = 1;

Console::log($map->count(),$nonWeak);

unset($object,$key);

Console::log($map->count(),$nonWeak);
