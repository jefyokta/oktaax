<?php
namespace Oktaax\Core\Task;
interface Catchable {
    public static function catch(\Throwable $th);
}
