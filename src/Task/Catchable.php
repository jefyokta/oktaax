<?php
namespace Oktaax\Task;
interface Catchable
{
    /**
     * Handle a failure inside the task worker. Returning normally marks the failure handled; throwing replaces it.
     *
     * @return mixed
     */
    public static function catch(\Throwable $th);
}
