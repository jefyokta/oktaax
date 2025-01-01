<?php

use Oktaax\Http\Response;
use Oktaax\Http\Support\Validation;
use Oktaax\Websocket\Server;
use Oktaax\Interfaces\Channel;
use Oktaax\Oktaax;
use Oktaax\Trait\HasWebsocket;
use Oktaax\Websocket\Client;
use OpenSwoole\Coroutine;
use OpenSwoole\Http\Server as OpenSwooleServer;

require_once "vendor/autoload.php";

class Even implements Channel
{

    public function eligible(Client $client): bool
    {

        return $client->fd % 2 === 0;
    }
}

$app = new class extends Oktaax {
    // use HasWebsocket;
};

// $app->ws('even', function (Server $server, Client $client) {
//     $server->reply(['name'=>'even','client'=>$client]);
// });

$array = ["jefy", 'okta', 'mipa', 'ipang', 'bangpan', 'alet', 'pupun', 'cibin'];

$app->get("/", function ($req, Response $res) use ($array) {

    foreach ($array as $a) {
        $res->write($a . "\n");
        Coroutine::sleep(1);
    }
    $res->end();
});

$app->listen(3000, function ($url) {
    echo "started at {$url}";
});






// use Swoole\Coroutine;

// $directory = 'src';
// $files = [];

// $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
// foreach ($iterator as $file) {
//     if ($file->isFile() && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
//         $files[] = $file->getPathname();
//     }
// }
// Coroutine::run(function () use ($files) {
//     $license =  Coroutine::readFile("watermark");
//     foreach ($files as $file) {
//         go(function () use ($file, $license) {
//             $p =  Coroutine::readFile($file);
//             if (!str_contains($p, $license) && str_starts_with($p, "<?php")) {
//                 $r = str_replace('<?php', '', $p);
//                 Coroutine::writeFile($file, "<?php \n" . $license . "\n" . $r);
//             }
//         });
//     }
// });
