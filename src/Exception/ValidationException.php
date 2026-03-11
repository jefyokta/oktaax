<?php

namespace Oktaax\Exception;

class ValidationException extends \Exception
{
    public function __construct(private $data) {}

    public function getData(){
        return $this->data;
    }
};
