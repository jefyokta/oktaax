<?php

namespace Oktaax\Contracts;

class JsonScheme
{
    public function __construct(private array $data = [], private string $message = '', private array $error = []) {}
    public function encode(): string
    {
        return json_encode($this->toArray());
    }
    protected function toArray(): array
    {
        return [
            "message" => $this->message,
            "error" => $this->error,
            "data" => $this->data
        ];
    }
}



