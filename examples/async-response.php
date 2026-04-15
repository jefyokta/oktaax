<?php
require __DIR__ . "/../vendor/autoload.php";

use Oktaax\Attributes\Async;
use Oktaax\Console;
use Oktaax\Contracts\Middleware;
use Oktaax\Core\Application;
use Oktaax\Core\Promise\Promise;
use Oktaax\Exception\PromiseException;
use Oktaax\Http\CallableWrapper;
use Oktaax\Http\Request;
use Oktaax\Http\Response;
use Oktaax\Oktaax;

use function Oktaax\Utils\await;
use function Oktaax\Utils\fetch;
use function Oktaax\Utils\setTimeout;
use function Swoole\Coroutine\run;

$app = new Oktaax;

class AsyncMiddleware implements Middleware
{
    public function handle(Request $request, Response $response, $next)
    {
        $promise = promise(1);
        $request->start = microtime(true);

        $promise->then(function ($result) use (&$response) {
            $response->header("x-async-middleware", $result);
            $response->type("text/plain");
        });
        //always return $next to run next handler
        return $next();
    }
}

$app->use(new AsyncMiddleware());


/**
 * Summary of promise
 * @param int $number
 * @return Promise<int>
 */
function promise(int $number)
{
    /** @var Promise<int> */
    return new Promise(function ($res, $rej) use ($number) {
        setTimeout(function () use ($number, $rej, $res) {
            // $rej("oops");
            $res($number);
        }, 2000);
    });
}

// correct implement
// check it out, wont took (12 *2) sec
$app->get("/", function (): Promise {
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

function httpGet(string $host, string $path): Promise
{
    return new Promise(function ($res, $rej) use ($host, $path) {
        $start = microtime(true);
        $cli = new Swoole\Coroutine\Http\Client($host, 80);

        $cli->set([
            'timeout' => 5
        ]);

        $cli->get($path);

        $body = $cli->body;

        $cli->close();
        Console::log("took %.2f %s", microtime(true) - $start, 's');

        $res(json_decode($body));
    });
}
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

//bad if u wishing promise1 & 2 run paralle
$app->get("/await-blocking-async-2", function (Response $response) {
    //the result 1 will have blocking exection
    $result = await(promise(1));
    //the code below will executed after 2s
    $result2 = await(promise(2));
    $response->end($result . $result2);
});


$app->get(
    "/async-2",
    function (Response $response) {
        try {
            $p1 = promise(1);
            $p2 = promise(2);
            $result = await($p1);
            $result2 = await($p2);

            $response->end($result . $result2);
        } catch (\Throwable $th) {
            //throw $th;
            Console::error($th->getMessage());

            return $response->status(500)->end($th->getMessage());
        }
    }
);

$app->get(
    "/async-without-await",
    //without Async attribute, application will preventing that we return null/void and respnse is still writable, client will get no content. its due to unwaiting promise task
    #[Async]
    function (Response $response, Request $request) {
        $promise = promise(2);

        $promise->then(function ($t) use (&$response) {
            $response->header('x-async', 1)->end($t);
        })->catch(function ($e) use (&$response) {
            $response->header('x-async-error', 1)
                ->status(500)
                ->type('text/plain')
                ->end($e->getMessage());
        })->finally(function () use (&$request) {
            Console::log("response send", microtime(true) - $request->start);
        });
    }
);
$app->get("/http-client", function (Response $res) {

    $start = microtime(true);

    $results = await(Promise::all([
        fetch("http://127.0.0.1:3001/delay/1?req=1")->then(fn($t) => $t->json()),
        fetch("http://127.0.0.1:3001/delay/1?req=2")->then(fn($t) => $t->json()),
        fetch("http://127.0.0.1:3001/delay/1?req=3")->then(fn($t) => $t->json()),
    ]));

    $end = microtime(true);
    Console::info("all task took %.2f%s", $end - $start, "s");
    $res->json([
        "result" => $results,
        "took" => $end - $start
    ]);
});
$app->listen(3000);
