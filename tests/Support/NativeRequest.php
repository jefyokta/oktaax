<?php

namespace Tests\Support;

class NativeRequest extends \Swoole\Http\Request
{
    public string|false $raw = '';
    public int $bodyReads = 0;

    public function rawContent(): string|false
    {
        $this->bodyReads++;
        return $this->raw;
    }
}
