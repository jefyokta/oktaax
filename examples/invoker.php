<?php

use Oktaax\Console;
use Oktaax\Utils\Invoker;

require_once __DIR__ . "/../vendor/autoload.php";

$invoker = new Invoker;
class Test
{
    public $name = "test";
    public $type = self::class;
}

$s = new stdClass;
$s->type = $s::class;

$invoker
    ->addContext($s)
    ->addContext(new Test);;



$stdClass = function (stdClass $obj) {
    Console::group("std");

    Console::log($obj);
    Console::groupEnd();
};

$testFunc = function (Test $t) {
    Console::group("test");

    Console::log($t);
    Console::groupEnd();
};
$testStdFunc = function (Test $t, stdClass $std) {
    Console::group("testStd");
    $std->test = $t;
    $std->std = clone $std;
    Console::log($t, $std);
    Console::groupEnd();
};

// when the function is called, invoker will destoroy all context, so we need to clone or re assign the context
//in example below im cloning the invoker 
$invoker2 = clone $invoker;
$invoker3 = clone $invoker;
$invoker->call($stdClass);
$invoker2->call($testFunc);
$invoker3->call($testStdFunc);
