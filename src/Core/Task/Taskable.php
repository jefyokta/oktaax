<?php

namespace Oktaax\Core\Task;

use TReturn;


/**
 * @template TReturn
 * 
 * 
 */
interface Taskable
{
    /**
     * 
     * this method will be called in task event
     * @return TReturn
     */
    public function handle();
}
