<?php

use Oktaax\Websocket\Client;
use Oktaax\Websocket\Event;

class EventTest extends Event
{

    public function message()
    {
        return ["name" => "jefy"];
    }
}

test("Event Test", function () {


    $t = (new EventTest)->client(new Client(3))->__toString();
    expect($t)->toBeString();
});
