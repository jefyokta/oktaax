<?php
require __DIR__ . "/../vendor/autoload.php";

use Oktaax\Attributes\Async;
use Oktaax\Console;
use Oktaax\Core\Promise\Promise;
use Oktaax\Http\Response;
use Oktaax\Oktaax;

use function Oktaax\Utils\await;
use function Oktaax\Utils\setTimeout;

$app = new Oktaax;


/**
 * Summary of promise
 * @param int $number
 * @return Promise<int>
 */
function promise(int $number)
{
    /** @var Promise<int> */
    return new Promise(function ($res, $rej) use ($number) {
        setTimeout(function () use ($number, $res) {
            $res($number);
        }, 2000);
    });
}

// correct implement
// check it out, wont took (12 *2) sec
$app->get("/", function () {
    return Promise::all([
        promise(1),
        promise(2),
        promise(3),
        promise(4),
        promise(5),
        promise(6),
        promise(7),
        promise(8),
        promise(9),
        promise(10),
        promise(11),
        promise(12),
    ]);
});


// wrong implement
$app->get("/bad-async-response", function (Response $response) {
    //application doesnt now that u running coroutine function here, will detect null return and response is still writtable
    go(function () use ($response) {
        $result = await(Promise::all([
            promise(1),
            promise(2),
            promise(3),
            promise(4),
            promise(5),
        ]));

        $response->json([
            'data' => $result,
            'time' => date('H:i:s')
        ]);
    });
});


$app->get("/bad-async-2", function (Response $response) {
    //the result 1 will have blocking exection
    $result = await(promise(1));
    //the code below will executed after 2s
    $result2 = await(promise(2));
    $response->end($result . $result2);
});


$app->get("/async-2",function (Response $response) {

        $p1 = promise(1);
        $p2 = promise(2);
        $result = await($p1);
        $result2 = await($p2);

        $response->end($result . $result2);
    }
);

$app->get("/async-without-await",function(Response $response){
    $promise = promise(2);

    $promise->then(function ($t) use ($response) {
        $response->end($t);
        var_dump($response,$t);
    })->finally(function () {
        Console::log("response send");
    });
});

$app->listen(3000);
