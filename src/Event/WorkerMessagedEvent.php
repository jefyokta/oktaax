<?php

namespace Oktaax\Event;


class WorkerMessagedEvent extends Event
{
    public function __construct(int $senderId, mixed $data)
    {
        parent::__construct("messaged", $data);
        $this->sender = [
            "name" => "worker." . $senderId,
            "id" => $senderId,
        ];
    }
}
