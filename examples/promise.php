<?php

use Oktaax\Console;
use Oktaax\Core\Promise\Promise;

use function Oktaax\Utils\async;
use function Oktaax\Utils\await;
use function Oktaax\Utils\setTimeout;

require_once __DIR__ . "/../vendor/autoload.php";



$promise = new Promise(function ($resolver, $p1) {
    setTimeout(function () use ($resolver) {
        $resolver("ok");
    }, 1000);
});
$asyncFn = async(function (int $number) use ($promise) {
    await($promise);
    Console::log($number);
});

$asyncFn(1);
