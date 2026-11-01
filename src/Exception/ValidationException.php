<?php

namespace Oktaax\Exception;

class ValidationException extends \Exception
{
    /**
     * Store application validation failures; callers may supply field messages or nested rule/message maps.
     *
     * @param array<string, string|array<string, string>> $errors
     */
    public function __construct(private $errors) {}

    /**
     * Return the supplied validation error map.
     *
     * @return array<string, string|array<string, string>>
     */
    public function getErrors()
    {
        return $this->errors;
    }
}
