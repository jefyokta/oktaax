<?php

namespace Oktaax\Core\Task;

/**
 * @template T
 */
interface Completable {

    /**
     * Run in onfinish event, after task is done
     * @param T $result
     * @return void
     */
    public static function onComplete($result):void;
}
