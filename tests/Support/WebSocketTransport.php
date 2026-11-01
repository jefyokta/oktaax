<?php

namespace Tests\Support;

use Oktaax\Websocket\Transport;

final class WebSocketTransport implements Transport
{
    public array $open = [];
    public array $sent = [];
    public array $closed = [];
    public array $fail = [];
    public function send(int $fd, string $payload, int $opcode): bool
    {
        if (isset($this->fail[$fd])) {
            return false;
        }
        $this->sent[$fd][] = ['payload' => $payload, 'opcode' => $opcode];
        return true;
    }
    public function close(int $fd, int $code, string $reason): bool
    {
        $this->closed[$fd] = [$code, $reason];
        unset($this->open[$fd]);
        return true;
    }
    public function isOpen(int $fd): bool
    {
        return isset($this->open[$fd]);
    }
    public function messages(int $fd): array
    {
        return array_map(
            fn(array $packet) => json_decode(
                $packet['payload'],
                true,
                flags: JSON_THROW_ON_ERROR,
            ),
            $this->sent[$fd] ?? [],
        );
    }
}
