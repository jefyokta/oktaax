<?php
require_once __DIR__."/../vendor/autoload.php";

use Oktaax\Console;
use Oktaax\Core\Promise\Promise;
use Oktaax\Exception\PromiseException;
use Swoole\Coroutine\Client;

use Swoole\Process;
use function Oktaax\Utils\async;
use function Oktaax\Utils\fetch;



//must run server-delay-response.php first
Console::time("main");
async(function () {
    $start = microtime(true);
    fetch("http://localhost:3000/delay/4")->then(fn($r) => $r->json())->then(function ($t) use ($start) {
        Console::log($t);
        Console::info("json took %.2f ", microtime(true) - $start);;
    })->catch(function ( $error) {
       Console::error((string)$error);
    });
    fetch("http://localhost:3000/delay/4")->then(fn($r) => $r->text())->then(function ($t) use ($start) {
        Console::log($t);
        Console::info("text took %.2f ", microtime(true) - $start);;;;
    })->catch(function ( $error) {
       Console::error((string)$error);
    });
    fetch("http://localhost:3000/delay/4")->then(fn($r) => $r->bytes())->then(function ($t) use ($start) {
        Console::log($t);
        Console::info("bytes took %.2f ", microtime(true) - $start);;
    })->catch(function ($error) {
       Console::error((string)$error);
    });
})();

Console::timeEnd("main");
