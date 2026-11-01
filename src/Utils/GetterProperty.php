<?php

namespace Oktaax\Utils;

use TReturn;

/**
 * Defer a computed property callback until its owning Struct reads the property.
 *
 * @template TValue
 */
class GetterProperty
{
    /**
     * Store a zero-argument getter that is later bound to the owning Struct.
     *
     * @param \Closure(): TValue $callback
     */
    public function __construct(private \Closure $callback) {}

    /**
     * Return the getter without evaluating it.
     *
     * @return \Closure(): TValue
     */
    public function getCallback()
    {
        return $this->callback;
    }
}
