<?php


require_once __DIR__ . '/../vendor/autoload.php';


$app =  new class extends Oktaax\Oktaax {
    use Oktaax\Trait\Laravelable;
};

$app->laravelRegister(
    (new \Oktaax\Types\Laravel)->withDomain("absensi.test")->withDirectory(__DIR__ . "/../../absensi/"),
    (new \Oktaax\Types\Laravel)->withDomain("jepi.okta")->withDirectory(__DIR__ . "/../test/")
);

$app->listen(3000, '127.0.0.1', function ($url) {
    echo "Server started at $url \n";
});
