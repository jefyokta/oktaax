<?php


namespace Oktaax\Interfaces;

use Oktaax\Websocket\Client;

interface Channel
{

    public function eligible(Client $client): bool;
};
