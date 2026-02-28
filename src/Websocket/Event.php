<?php

namespace Oktaax\Websocket;

use Stringable;

abstract class Event implements Stringable
{

    protected $client;
    protected function message()
    {
        return "";
    }
    public function __toString(): string
    {
        return json_encode([
            "event" => basename(str_replace('\\', '.', static::class)),
            "message" => $this->message(),
        ]);
    }

    public function client(Client $client)
    {

        $this->client = $client;
        return $this;
    }
}
