<?php

namespace Oktaax\Http\Client;

use Oktaax\Core\Promise\Promise;
use Oktaax\Exception\PromiseException;
use Oktaax\Http\Headers;
use Swoole\Coroutine;
use Swoole\Coroutine\Client;

use function Oktaax\Utils\spawn;

class Response
{
    private bool $bodyUsed = false;
    private bool $closed = false;
    public  readonly bool $ok;

    private string $buffer = '';

    public function __construct(
        private Client $cli,
        public int $status,
        public Headers $headers,
        string $initialBuffer = ''
    ) {
        $this->buffer = $initialBuffer;
        $this->ok = $this->status >= 200 && $this->status < 300;
    }

    public function ok(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    private function recv(): ?string
    {
        if ($this->buffer !== '') {
            $chunk = $this->buffer;
            $this->buffer = '';
            return $chunk;
        }

        $data = $this->cli->recv();
        if ($data === '' || $data === false) return null;

        return $data;
    }

    /**
     * @return Promise<string>
     */
    public function text(): Promise
    {
        return new Promise(function ($resolve, $reject) {

            spawn(function () use ($resolve, $reject) {

                if ($this->bodyUsed) {
                    $reject("Body already used");
                    return;
                }

                $this->bodyUsed = true;

                try {
                    $len = $this->headers->get("content-length") ?? null;
                    $chunked = ($this->headers->get('transfer-encoding') ?? '') === 'chunked';

                    if ($chunked) {
                        $body = $this->readChunked();
                    } elseif ($len !== null) {
                        $body = $this->readContentLength((int)$len);
                    } else {
                        $body = $this->readUntilClose();
                    }

                    $this->close();
                    $resolve($body);
                } catch (\Throwable $e) {
                    $this->close();
                    $reject($e);
                }
            });
        });
    }

    /** 
     * @return  Promise<\stdClass>
     */

    public function json(): Promise
    {
        return $this->text()->then(function ($text) {
            $data = json_decode($text);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new PromiseException(substr($text, 0, strlen($text) > 100 ? 100 : \strlen($text)) . "\n" . "is not a valid json");
            }

            return $data;
        });
    }
    /**
     * @return Promise<int>
     */

    public function bytes(): Promise
    {
        return $this->text()->then(function (string $raw) {
            return array_values(unpack('C*', $raw) ?: []);
        });
    }
    private function readUntilClose(): string
    {
        $body = '';

        while ($chunk = $this->recv()) {
            $body .= $chunk;
        }

        return $body;
    }

    private function readContentLength(int $length): string
    {
        $body = '';

        while (strlen($body) < $length) {
            $chunk = $this->recv();
            if ($chunk === null) break;

            $body .= $chunk;
        }

        return substr($body, 0, $length);
    }

    private function readLine(): ?string
    {
        while (!str_contains($this->buffer, "\r\n")) {
            $chunk = $this->cli->recv();
            if ($chunk === '' || $chunk === false) return null;
            $this->buffer .= $chunk;
            Coroutine::sleep(0.001);
        }

        [$line, $this->buffer] = explode("\r\n", $this->buffer, 2);
        return $line;
    }

    private function readChunked(): string
    {
        $body = '';

        while (true) {
            $line = $this->readLine();
            if ($line === null) break;

            $len = hexdec(trim($line));
            if ($len === 0) break;

            $chunk = '';
            while (strlen($chunk) < $len) {
                $part = $this->recv();
                if ($part === null) break;
                $chunk .= $part;
                // Coroutine::sleep(0.001);
            }

            $body .= substr($chunk, 0, $len);

            $this->recv();
            // Coroutine::sleep(0.001);
        }

        return $body;
    }

    public function close(): void
    {
        if (!$this->closed) {
            $this->cli->close();
            $this->closed = true;
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    public function formData(): Promise
    {
        return $this->text()->then(function (string $raw) {
            $contentType = $this->headers->get('content-type') ?? '';

            if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
                return $this->parseUrlEncoded($raw);
            }

            if (str_contains($contentType, 'multipart/form-data')) {
                preg_match('/boundary=([^\s;]+)/', $contentType, $m);
                $boundary = $m[1] ?? null;

                if ($boundary === null) {
                    throw new PromiseException('multipart/form-data: boundary not found in Content-Type header');
                }

                return $this->parseMultipart($raw, trim($boundary, '"'));
            }

            throw new PromiseException(
                "Unsupported content-type for formData(): '{$contentType}'. " .
                    "Expected 'application/x-www-form-urlencoded' or 'multipart/form-data'."
            );
        });
    }

    private function parseUrlEncoded(string $raw): array
    {
        parse_str($raw, $parsed);
        return $parsed;
    }


    private function parseMultipart(string $raw, string $boundary): array
    {
        $result = [];

        $raw = str_replace("\r\n", "\n", $raw);
        $raw = str_replace("\r", "\n", $raw);
        $raw = str_replace("\n", "\r\n", $raw);

        $delimiter = "--{$boundary}";
        $parts     = explode($delimiter, $raw);

        array_shift($parts); 
        array_pop($parts);   

        foreach ($parts as $part) {
            $part = ltrim($part, "\r\n");
            $part = rtrim($part, "\r\n");

            $headerBodySplit = strpos($part, "\r\n\r\n");
            if ($headerBodySplit === false) continue;

            $rawPartHeaders = substr($part, 0, $headerBodySplit);
            $body           = substr($part, $headerBodySplit + 4); 

            $partHeaders = $this->parsePartHeaders($rawPartHeaders);

            $disposition = $partHeaders['content-disposition'] ?? '';
            if (!str_contains($disposition, 'form-data')) continue;

            $fieldName = $this->extractDispositionParam($disposition, 'name');
            if ($fieldName === null) continue;

            $filename    = $this->extractDispositionParam($disposition, 'filename');
            $partMime    = $partHeaders['content-type'] ?? null;

            if ($filename !== null) {
                $value = [
                    'filename'     => $filename,
                    'content_type' => $partMime ?? 'application/octet-stream',
                    'size'         => strlen($body),
                    'data'         => $body,
                ];
            } else {
                $value = $this->decodePartValue($body, $partMime);
            }

            $this->assignField($result, $fieldName, $value);
        }

        return $result;
    }


    private function parsePartHeaders(string $raw): array
    {
        $headers = [];
        $lines   = explode("\r\n", $raw);

        foreach ($lines as $line) {
            if (!str_contains($line, ':')) continue;

            [$name, $value] = explode(':', $line, 2);
            $headers[strtolower(trim($name))] = trim($value);
        }

        return $headers;
    }

    private function extractDispositionParam(string $disposition, string $param): ?string
    {
        if (preg_match("/{$param}\*=([^;]+)/i", $disposition, $m)) {
            $extValue = trim($m[1]);
            if (preg_match("/^([^']*)'([^']*)'(.+)$/", $extValue, $parts)) {
                $charset = strtoupper($parts[1]) ?: 'UTF-8';
                $encoded = $parts[3];
                $decoded = rawurldecode($encoded);

                return $charset !== 'UTF-8'
                    ? mb_convert_encoding($decoded, 'UTF-8', $charset)
                    : $decoded;
            }
        }

        if (preg_match("/{$param}=\"([^\"]*)\"/i", $disposition, $m)) {
            return $m[1];
        }

        if (preg_match("/{$param}=([^;\\s]+)/i", $disposition, $m)) {
            return $m[1];
        }

        return null;
    }


    private function decodePartValue(string $value, ?string $contentType): string
    {
        if ($contentType === null) {
            return $value;
        }

        preg_match('/charset=([^\s;]+)/i', $contentType, $m);
        $charset = strtoupper(trim($m[1] ?? 'UTF-8', '"\''));

        if ($charset === 'UTF-8') {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', $charset) ?: $value;
    }


    private function assignField(array &$result, string $field, mixed $value): void
    {
        if (!isset($result[$field])) {
            $result[$field] = $value;
            return;
        }

        if (!is_array($result[$field]) || isset($result[$field]['data'])) {
            $result[$field] = [$result[$field]];
        }

        $result[$field][] = $value;
    }
}
