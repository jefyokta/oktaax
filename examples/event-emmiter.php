<?php

use Oktaax\Event\EventEmitter;

require __DIR__ . '/../vendor/autoload.php';

$emiter = new EventEmitter;

$counter = 0;

$emiter->on("test", function () use (&$counter) {
    echo ++$counter . PHP_EOL;
});



$emiter->once("test", function () use (&$counter) {
    echo ++$counter . PHP_EOL;
});

$child = new class extends EventEmitter {};

$child->on("test", function () {
    echo "child\n";
});


echo $child->listenerCount('test') . "\n";
$child->emit("test");




$emiter->emit("test");
$emiter->emit("test");
$emiter->emit("test");
