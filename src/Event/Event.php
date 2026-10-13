<?php

namespace Oktaax\Event;

/**
 * @template  T of mixed
 */
class Event
{

    public  readonly string $name;

    public function __construct(
        string $eventName,
        /** @var  T */
        public mixed $data
    ) {
        $this->name = $eventName;
    }
}
