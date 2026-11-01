<?php

namespace Oktaax\Event;

/**
 * A named application event carrying a payload and optional worker sender metadata.
 *
 * @template T
 */
class Event
{
    public readonly string $name;

    /**
     * Sender metadata attached by IPC-aware emitters; absent for ordinary local events.
     *
     * @var array{name: string, id: int}|null
     */
    public ?array $sender = null;

    /**
     * Create an event value without looking up an active application.
     *
     * @param T $data
     */
    public function __construct(
        string $eventName,
        /** @var  T */
        public mixed $data,
    ) {
        $this->name = $eventName;
    }
}
