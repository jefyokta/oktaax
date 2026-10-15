<?php

namespace Oktaax\Exception;


use RuntimeException;
use Throwable;

final class EventDispatchException extends RuntimeException
{
    /**
     * @param list<Throwable> $exceptions
     */
    public function __construct(
        public readonly string $event,
        private readonly array $exceptions,
    ) {
        parent::__construct(
            sprintf(
                'Event "%s" failed with %d listener exception(s).',
                $event,
                count($exceptions),
            ),
            0,
            $exceptions[0] ?? null,
        );
    }

    /**
     * @return list<Throwable>
     */
    public function exceptions(): array
    {
        return $this->exceptions;
    }
}

