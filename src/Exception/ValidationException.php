<?php

namespace Oktaax\Exception;

class ValidationException extends \Exception
{
    public function __construct(private $errors) {}

    public function getErrors(){
        return $this->errors;
    }
};
