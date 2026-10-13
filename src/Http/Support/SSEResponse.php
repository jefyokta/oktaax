<?php

namespace Oktaax\Http\Support;

use InvalidArgumentException;
use Oktaax\Http\Response;
use JsonException;
use Oktaax\Event\EventEmitter;
use RuntimeException;

class SSEResponse
{
    private bool $started = false;

    private bool $stopped = false;

    private Response $response;

    private EventEmitter $eventEmitter;


    public function __construct(public array $headers = [])
    {
        $this->eventEmitter = new EventEmitter;
        $this->response = \Oktaax\Core\Application::getResponse();
    }


    /**
     * Summary of on
     * @param "close"|"start" $event
     * @param mixed $listener
     * @return void
     */
    public function on(string $event, $listener)
    {
        $this->eventEmitter->on($event, $listener);
    }

    public function start(?Response $response = null): void
    {
        if ($this->started) {
            return;
        }

        if ($this->stopped) {
            throw new RuntimeException(
                'Cannot start a stopped SSE connection.'
            );
        }

        if ($response !== null) {
            $this->response = $response;
        }

        $this->response->preventEnd();


        $this->response->header('Content-Type', 'text/event-stream; charset=utf-8');
        $this->response->header('Cache-Control', 'no-cache');
        $this->response->header('X-Accel-Buffering', 'no');
        foreach ($this->headers as $key => $value) {
            $this->response->header($key, $value);
        }
        $this->response->sendHeaders();

        $this->started = true;
        $this->eventEmitter->emit("start");
    }

    /**
     * Send an SSE message.
     *
     * Supported options:
     * - event: string
     * - id: string|int
     * - retry: int
     * - comment: string
     *
     * @throws JsonException
     */
    public function send(
        mixed $data,
        array $options = [],
    ): void {

        try {
            $this->assertWritable();
        } catch (\Throwable $th) {
            $this->stop();
            $this->eventEmitter->emit("close", ["error" => $th]);
        }

        if (isset($options['event'])) {
            $this->writeField('event', (string) $options['event']);
        }

        if (isset($options['id'])) {
            $id = (string) $options['id'];

            if (str_contains($id, "\0")) {
                throw new InvalidArgumentException(
                    'SSE event ID cannot contain a null character.'
                );
            }

            $this->writeField('id', $id);
        }

        if (isset($options['retry'])) {
            if (
                !\is_int($options['retry'])
                || $options['retry'] < 0
            ) {
                throw new InvalidArgumentException(
                    'SSE retry must be a non-negative integer.'
                );
            }

            $this->writeField(
                'retry',
                (string) $options['retry']
            );
        }

        $this->writeData($data);
    }

    /**
     * Send a comment, commonly used as a heartbeat.
     */
    public function comment(string $comment = 'heartbeat'): void
    {
        $this->assertWritable();

        foreach ($this->splitLines($comment) as $line) {
            $this->response->write(": {$line}\n");
        }

        $this->response->write("\n");
    }

    /**
     * End the SSE connection.
     */
    public function stop(): void
    {
        if ($this->stopped) {
            return;
        }

        $this->stopped = true;

        if ($this->started && $this->response->isWritable()) {
            $this->response->swoole()->end();
        }
        $this->eventEmitter->emit("close");
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
        if (!$this->started) {
            throw new RuntimeException(
                'SSE connection has not been started.'
            );
        }

        if ($this->stopped || !$this->response->isWritable()) {
            throw new RuntimeException(
                'SSE connection is no longer writable.'
            );
        }
    }

    private function writeData(mixed $data): void
    {
        $payload = is_string($data)
            ? $data
            : json_encode(
                $data,
                JSON_THROW_ON_ERROR
                    | JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES,
            );

        foreach ($this->splitLines($payload) as $line) {
            $this->response->write("data: {$line}\n");
        }

        $this->response->write("\n");
    }

    private function writeField(string $name, string $value): void
    {
        if (preg_match('/[\r\n\0]/', $value)) {
            throw new InvalidArgumentException(
                "SSE {$name} cannot contain newline or null characters."
            );
        }

        $this->response->write("{$name}: {$value}\n");
    }

    /**
     * @return list<string>
     */
    private function splitLines(string $value): array
    {
        return preg_split('/\r\n|\r|\n/', $value);
    }
}
