<?php

namespace Tests\Support;

class NativeResponse extends \Swoole\Http\Response
{
    public array $calls = [];
    public array $sentHeaders = [];
    public int $sentStatus = 200;
    public ?string $sentBody = null;
    public bool $writable = true;
    public bool $writeSucceeds = true;

    public function isWritable(): bool
    {
        return $this->writable;
    }

    public function header(
        string $key,
        array|string $value,
        bool $format = true,
    ): bool {
        $this->calls[] = 'header';
        $this->sentHeaders[strtolower($key)] = $value;
        return true;
    }

    public function status(int $http_code, string $reason = ''): bool
    {
        $this->calls[] = 'status';
        $this->sentStatus = $http_code;
        return true;
    }

    public function write(string $content): bool
    {
        $this->calls[] = 'write';
        if ($this->writeSucceeds) {
            $this->sentBody = ($this->sentBody ?? '') . $content;
        }
        return $this->writeSucceeds;
    }

    public function end(?string $content = null): bool
    {
        $this->calls[] = 'end';
        $this->sentBody = ($this->sentBody ?? '') . ($content ?? '');
        $this->writable = false;
        return true;
    }

    public function sendfile(
        string $filename,
        int $offset = 0,
        int $length = 0,
    ): bool {
        $this->calls[] = 'sendfile';
        $this->sentBody = substr(
            file_get_contents($filename),
            $offset,
            $length ?: null,
        );
        $this->writable = false;
        return true;
    }
}
