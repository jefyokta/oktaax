<?php

namespace Oktaax\Exception;

class HttpException extends \RuntimeException
{
    public function __construct(private int $statusCode, protected $message = null) {}

    function getStatusCode()
    {
        return $this->statusCode;
    }
    function getHttpMessage()
    {
        return $this->message;
    }
}
