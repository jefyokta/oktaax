<?php

namespace Oktaax\Trait;

use Swoole\Http\Request;
use Swoole\Http\Response;

trait WebsocketAuthenticable
{

    private $wsKey;

    public function setWsKey($key)
    {
        $this->wsKey = $key;
    }

    public function authentication()
    {
        // $this->server->on("handshake", function (Request $request, Response $response) {
        //     echo "okokok";
        //     $response->status(101);
        // });
    }
};
