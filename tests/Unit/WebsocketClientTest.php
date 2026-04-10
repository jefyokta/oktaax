<?php

use Oktaax\Websocket\Client;
use Oktaax\Interfaces\Channel;

class AlwaysChannel implements Channel
{
    public function eligible(Client $client): bool
    {
        return true;
    }
}

it('decodes json data in Client constructor', function () {
    $client = new Client(12, json_encode(['foo' => 'bar']));

    expect($client->fd)->toBe(12);
    expect($client->data)->toBe(['foo' => 'bar']);
});

it('inChannel works with channel object', function () {
    $client = new Client(22);

    expect($client->inChannel(new AlwaysChannel()))->toBeTrue();
});
