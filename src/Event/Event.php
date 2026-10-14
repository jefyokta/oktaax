<?php

namespace Oktaax\Event;

use function Oktaax\Utils\app;

/**
 * @template  T of mixed
 */
class Event
{

    public  readonly string $name;

    public readonly ?array $sender;

    public function __construct(
        string $eventName,
        /** @var  T */
        public mixed $data
    ) {
        $this->name = $eventName;

        if (app()) {
            $this->sender = [
                "name" => app()->worker->name,
                "id" => app()->worker->id
            ];
        }
    }
}
