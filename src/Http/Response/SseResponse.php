<?php

namespace Oktaax\Http\Response;

use InvalidArgumentException;
use Oktaax\Event\EventEmitter;
use Oktaax\Http\Response;
use RuntimeException;

class SseResponse
{
    private bool $started = false;
    private bool $stopped = false;
    private EventEmitter $events;
    private ?\Throwable $error = null;

    /**
     * Create an SSE value with optional explicit response binding; construction does not start it.
     *
     * @param array<string, string> $headers
     */
    public function __construct(
        public array $headers = [],
        private ?Response $response = null,
    ) {
        $this->events = new EventEmitter();
    }

    /**
     * Register a local SSE lifecycle listener for start or close.
     *
     * @param callable(\Oktaax\Event\Event<mixed>): mixed $listener
     */
    public function on(string $event, callable $listener): void
    {
        $this->events->on($event, $listener);
    }

    /**
     * Start once using an explicitly supplied or previously bound response; detach automatic HTTP completion.
     *
     * @throws \RuntimeException When no response is bound or the stream was already stopped.
     */
    public function start(?Response $response = null): void
    {
        if ($this->started) {
            return;
        }
        if ($this->stopped) {
            throw new RuntimeException(
                'Cannot start a stopped SSE connection.',
            );
        }
        $this->response = $response ?? $this->response;
        $res =
            $this->response ??
            throw new RuntimeException(
                'An SSE response requires an HTTP response.',
            );
        $res->preventEnd();
        $res->header('content-type', 'text/event-stream; charset=utf-8');
        $res->header('cache-control', 'no-cache');
        $res->header('x-accel-buffering', 'no');
        foreach ($this->headers as $name => $value) {
            $res->header($name, $value);
        }
        $res->sendHeaders();
        $res->onResponseClosed(function ($event) {
            if ($this->stopped) {
                return;
            }
            $this->stopped = true;
            try {
                $this->events->emit('close', [
                    'error' => $this->error,
                    'aborted' => $event->data['aborted'],
                ]);
            } finally {
                $this->events->clear();
            }
        });
        $res->connection()->watch(fn() => $res->swoole()->isWritable(), 1000);
        $this->started = true;
        $this->events->emit('start');
    }

    /**
     * Write one SSE data frame; JSON-encode nonstrings and return the native write result.
     *
     * @param array{event?: string, id?: string, retry?: int} $options
     * @throws \InvalidArgumentException When event fields or retry values violate SSE framing.
     * @throws \JsonException When payload encoding fails.
     * @throws \RuntimeException When the stream is not writable.
     */
    public function send(mixed $data, array $options = []): bool
    {
        $this->assertWritable();
        $frame = '';
        foreach (['event', 'id'] as $name) {
            if (isset($options[$name])) {
                $value = (string) $options[$name];
                if (preg_match('/[\r\n\0]/', $value)) {
                    throw new InvalidArgumentException("Invalid SSE {$name}.");
                }
                $frame .= "{$name}: {$value}\n";
            }
        }
        if (isset($options['retry'])) {
            if (!is_int($options['retry']) || $options['retry'] < 0) {
                throw new InvalidArgumentException(
                    'SSE retry must be a non-negative integer.',
                );
            }
            $frame .= 'retry: ' . $options['retry'] . "\n";
        }
        $payload = is_string($data)
            ? $data
            : json_encode(
                $data,
                JSON_THROW_ON_ERROR |
                    JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES,
            );
        foreach ($this->lines($payload) as $line) {
            $frame .= "data: {$line}\n";
        }
        return $this->response->write($frame . "\n");
    }

    public function comment(string $comment = 'heartbeat'): bool
    {
        $this->assertWritable();
        $frame = '';
        foreach ($this->lines($comment) as $line) {
            $frame .= ": {$line}\n";
        }
        return $this->response->write($frame . "\n");
    }

    public function stop(): void
    {
        if ($this->stopped) {
            return;
        }
        if ($this->started) {
            $this->response->end();
        } else {
            $this->stopped = true;
            $this->events->emit('close');
            $this->events->clear();
        }
    }

    public function isStarted(): bool
    {
        return $this->started;
    }
    public function isStopped(): bool
    {
        return $this->stopped;
    }

    private function assertWritable(): void
    {
        if (
            !$this->started ||
            $this->stopped ||
            !$this->response->isWritable()
        ) {
            throw new RuntimeException('SSE connection is not writable.');
        }
    }
    /**
     * Split CR, LF, or CRLF payload lines for SSE framing.
     *
     * @return list<string>
     */
    private function lines(string $data): array
    {
        return preg_split('/\r\n|\r|\n/', $data);
    }
}
