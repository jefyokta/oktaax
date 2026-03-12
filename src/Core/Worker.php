<?php

namespace Oktaax\Core;


class Worker
{
    public function __construct(public $id, private $taskWorker = false) {}

    public function isTaskWorker()
    {

        return $this->taskWorker;
    }
};
