<?php

namespace Oktaax\Core\Event;

/**
 * @template TArgs
 */
abstract class Event
{
    abstract public function name(): string;

    /**
     * @param TArgs ...$args
     */
    abstract public function handle(...$args): void;
    /**
     * @param TArgs ...$args
     * @return TArgs
     */
    protected function unpack(...$args): array
    {
        return $args;
    }
}
