<?php

use Oktaax\Auditor\Factory;
use Oktaax\Auditor\Memory;
use Swoole\Http\Server;

test("Audit Factory calls memory auditor", function () {

    $server =  Mockery::mock(Server::class);

    $server->shouldReceive('getWorkerPid');

    $workerId = rand(1000, 10000);

    $result =   Factory::auditWith(Memory::class, $server, $workerId);

    expect($result)->toBeTrue();
});

test("audit Memory limit with more bla bla", function () {

    ini_set('memory_limit', '10M');
    $result =    Memory::setLimit(1100000);

    expect($result)->throw(error)
});
