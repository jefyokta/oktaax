<?php

namespace Oktaax\Http;


final class ResponseJson
{
    private array $data = [];

    public function __construct(?array $data = [], ?string $msg = null, $error = null)
    {

        $this->data = [
            "data" => $data,
            "error" => $error,
            "message" => $msg
        ];
    }

    public function get()
    {
        return $this->data;
    }
}
