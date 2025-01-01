<?php


require_once __DIR__ . '/../vendor/autoload.php';


$app =  new class extends Oktaax\Oktaax {
    use Oktaax\Trait\Laravelable;
};
$app->setServer("worker_num", 4);
$app->laravelRegister(
    new \Oktaax\Types\Laravel(
        directory:  "/Applications/jefyokta/esuratdesa"
    ),
);

$app->listen(3000, '127.0.0.1', function ($url) {
    echo "Server started at $url \n";
});
