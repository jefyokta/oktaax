<?php

namespace Tests\Support;

final class WebSocketPeer
{
    private mixed $socket;
    public int $status;
    public array $headers = [];

    public function __construct(
        int $port,
        string $path = '/ws',
        array $headers = [],
        string $key = '',
        string $version = '13',
    ) {
        $this->socket = stream_socket_client(
            'tcp://127.0.0.1:' . $port,
            $error,
            $message,
            5,
        );
        if ($this->socket === false) {
            throw new \RuntimeException($message);
        }
        stream_set_timeout($this->socket, 3);
        $key = $key !== '' ? $key : base64_encode(random_bytes(16));
        $lines = [
            "GET {$path} HTTP/1.1",
            'Host: 127.0.0.1',
            'Upgrade: websocket',
            'Connection: Upgrade',
            'Sec-WebSocket-Key: ' . $key,
            'Sec-WebSocket-Version: ' . $version,
            ...$headers,
        ];
        $this->write(implode("\r\n", $lines) . "\r\n\r\n");
        $response = '';
        while (!str_contains($response, "\r\n\r\n")) {
            $response .= $this->read(1);
        }
        $lines = explode("\r\n", trim($response));
        preg_match('/\s(\d{3})\s/', array_shift($lines), $match);
        $this->status = (int) $match[1];
        foreach ($lines as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $this->headers[strtolower($name)] = trim($value);
            }
        }
        if (
            $this->status === 101 &&
            ($this->headers['sec-websocket-accept'] ?? '') !==
                base64_encode(
                    sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true),
                )
        ) {
            throw new \RuntimeException('Invalid WebSocket accept header.');
        }
    }
    private function read(int $length): string
    {
        $result = '';
        while (strlen($result) < $length) {
            $chunk = fread($this->socket, $length - strlen($result));
            if ($chunk === false || $chunk === '') {
                throw new \RuntimeException(
                    'WebSocket stream ended or timed out.',
                );
            }
            $result .= $chunk;
        }
        return $result;
    }
    private function write(string $data): void
    {
        while ($data !== '') {
            $written = fwrite($this->socket, $data);
            if ($written === false || $written === 0) {
                throw new \RuntimeException('Could not write WebSocket data.');
            }
            $data = substr($data, $written);
        }
    }
    public function send(
        string $event,
        mixed $data = null,
        ?string $id = null,
    ): void {
        $message = ['event' => $event, 'data' => $data];
        if ($id !== null) {
            $message['id'] = $id;
        }
        $this->raw(json_encode($message, JSON_THROW_ON_ERROR));
    }
    public function raw(string $payload, int $opcode = 1): void
    {
        $length = strlen($payload);
        if ($length >= 65536) {
            throw new \InvalidArgumentException('Test frame is too large.');
        }
        $header =
            chr(0x80 | $opcode) .
            ($length < 126
                ? chr(0x80 | $length)
                : chr(0x80 | 126) . pack('n', $length));
        $mask = random_bytes(4);
        $masked = '';
        for ($index = 0; $index < $length; $index++) {
            $masked .= $payload[$index] ^ $mask[$index % 4];
        }
        $this->write($header . $mask . $masked);
    }
    public function frame(): array
    {
        $header = $this->read(2);
        $opcode = ord($header[0]) & 0x0f;
        $length = ord($header[1]) & 0x7f;
        if ($length === 126) {
            $length = unpack('n', $this->read(2))[1];
        }
        if ($length === 127) {
            throw new \RuntimeException('Unexpected large test response.');
        }
        return [$opcode, $length > 0 ? $this->read($length) : ''];
    }
    public function receive(): array
    {
        [$opcode, $payload] = $this->frame();
        if ($opcode !== 1) {
            throw new \RuntimeException('Expected text frame, got ' . $opcode);
        }
        return json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
    }
    public function hasData(int $milliseconds = 50): bool
    {
        $read = [$this->socket];
        $write = null;
        $except = null;
        return stream_select($read, $write, $except, 0, $milliseconds * 1000) >
            0;
    }
    public function close(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
        $this->socket = null;
    }
    public function __destruct()
    {
        $this->close();
    }
}
