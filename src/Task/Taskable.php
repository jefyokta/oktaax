<?php

namespace Oktaax\Task;

/**
 * A no-argument task handler executed in the task worker; payload values are assigned to existing public properties.
 *
 * @template TReturn
 */
interface Taskable
{
    /**
     * Execute task work in its owning task worker and return a serializable result.
     *
     * @return TReturn
     */
    public function handle();
}
