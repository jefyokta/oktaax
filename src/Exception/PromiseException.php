<?php

namespace Oktaax\Exception;

use Exception;


class PromiseException extends Exception
{
    public function __construct($message, ...$args)
    {
        parent::__construct("Uncaught in promise: {$message}", ...$args);
    }
}
