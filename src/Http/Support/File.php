<?php

namespace Oktaax\Http\Support;

class File
{

    public function __construct(private string $filePath, public readonly int $status = 200, private $headers = []) {}

    public function getFilePath()
    {

        return $this->filePath;
    }

    public function getHeaders()
    {

        return $this->headers;
    }
    public function withHeader(string $key, string $value)
    {
        $this->headers[$key] = $value;
        return $this;
    }
}
