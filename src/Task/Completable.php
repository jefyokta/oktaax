<?php

namespace Oktaax\Task;

/**
 * Optional completion hook invoked in the sending worker after successful task completion.
 *
 * @template TResult
 */
interface Completable
{
    /**
     * Observe a successful result; this callback also runs when the original waiter already timed out.
     *
     * @param TResult $result
     */
    public static function onComplete($result): void;
}
