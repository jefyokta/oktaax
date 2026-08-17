<?php

namespace Oktaax\Utils;

use TReturn;

/**
 * @template TReturn
 */
class GetterProperty
{

    /**
     * 
     * @param \Closure(...):TReturn $callback
     */
    public function __construct(private \Closure $callback) {}

    public function getCallback()
    {
        return $this->callback;
    }
}
