<?php

use Appx\Controller\Home;
use Oktaax\Http\Response;
use Oktaax\Http\Support\Validation;
use Oktaax\Websocket\Server;
use Oktaax\Interfaces\Channel;
use Oktaax\Oktaax;
use Oktaax\Trait\HasWebsocket;
use Oktaax\Websocket\Client;
use OpenSwoole\Coroutine;

require_once "vendor/autoload.php";

class Even implements Channel
{

    public function eligible(Client $client): bool
    {

        return $client->fd % 2 === 0;
    }
}

$app = new class extends Oktaax {
    use HasWebsocket;
};

$app->setServer('task_worker_num', 2);
$app->setServer('task_enable_coroutine', true);



$array = ["jefy", 'okta', 'mipa', 'ipang', 'bangpan', 'alet', 'pupun', 'cibin'];
$app->ws("a", function () {
    // xserver()->push("k",1);
});
$app->get("/",'Home.index');



$app->on('task', function () use ($app) {
    echo "\nworker doin task\n";

    go(function () use ($app) {

        Coroutine::writeFile('server_info', json_encode([xserver(),$app], JSON_PRETTY_PRINT));
    });
    // });

    return 1;
});
$app->on('finish', function ($serv, $id, $result) {
    // xserver()->push(1,$result);

    var_dump($result, ['fd' => $id]);
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
