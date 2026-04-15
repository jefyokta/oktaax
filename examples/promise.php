<?php

use Oktaax\Console;
use Oktaax\Core\Promise\Promise;

use function Oktaax\Utils\async;
use function Oktaax\Utils\await;
use function Oktaax\Utils\setTimeout;

require_once __DIR__ . "/../vendor/autoload.php";


//oktaax controller is running in swoole request event, which is is already in coroutine context
// but the async controller with void return should has async attribute so application didnt pretend the controller is return noting



//if u using promise here

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
