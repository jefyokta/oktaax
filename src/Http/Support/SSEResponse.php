<?php

namespace Oktaax\Http\Support;

use Closure;
use Exception;
use InvalidArgumentException;
use Oktaax\Console;
use Oktaax\Core\Application;
use Oktaax\Http\Response;
use Swoole\Coroutine;
use Throwable;
use function Oktaax\Utils\clearInterval;
use function Oktaax\Utils\setInterval;

class SSEResponse
{
    private int|string|null $intervalId = null;

    private bool $started = false;

    private Response $response;

    public function __construct(
        private readonly Closure $callback,
        private readonly int $interval = 1000,
    ) {
        if ($interval < 1) {
            throw new InvalidArgumentException(
                'SSE interval must be greater than zero.'
            );
        }

        $this->response = Application::getResponse();
    }

    public function start(Response $response): void
    {
        $this->response = $response;

        try {

            if ($this->started) {
                return;
            }

            $this->started = true;

            $this->response->swoole()->header(
                'Content-Type',
                'text/event-stream'
            );
            $this->response->swoole()->header(
                'Cache-Control',
                'no-cache'
            );
            $this->response->swoole()->header('X-Accel-Buffering', 'no');

            setInterval(function () {
                try {
                    $message = ($this->callback)();

                    if ($message === null || $message === false) {
                        return;
                    }

                    $this->writeMessage($message);
                    Console::log(($message));
                    Coroutine::sleep($this->interval);
                } catch (Throwable $exception) {

                    Console::error($exception);
                    throw $exception;
                }
            }, $this->interval);
        } catch (Throwable $th) {
            throw $th;
        }
    }

    /**
     * Encode and write an SSE message.
     *
     * Supported envelope:
     * [
     *     'data' => mixed,
     *     'event' => string|null,
     *     'id' => string|int|null,
     *     'retry' => int|null,
     * ]
     *
     * A comment-only message can use:
     * ['comment' => 'heartbeat']
     */
    private function writeMessage(mixed $message): void
    {
        $metadataKeys = ['data', 'event', 'id', 'retry', 'comment'];

        $isEnvelope = is_array($message)
            && array_intersect(
                $metadataKeys,
                array_keys($message)
            ) !== [];

        if (!$isEnvelope) {
            $this->writeData($message);

            return;
        }

        if (
            array_key_exists('comment', $message)
            && !array_key_exists('data', $message)
        ) {
            $this->writeComment((string) $message['comment']);

            return;
        }

        if (isset($message['event'])) {
            $this->writeField('event', (string) $message['event']);
        }

        if (isset($message['id'])) {
            $id = (string) $message['id'];

            if (str_contains($id, "\0")) {
                throw new InvalidArgumentException(
                    'SSE event ID cannot contain a null character.'
                );
            }

            $this->writeField('id', $id);
        }

        if (isset($message['retry'])) {
            if (
                !is_int($message['retry'])
                || $message['retry'] < 0
            ) {
                throw new InvalidArgumentException(
                    'SSE retry must be a non-negative integer.'
                );
            }

            $this->writeField('retry', (string) $message['retry']);
        }

        if (array_key_exists('data', $message)) {
            $this->writeData($message['data']);

            return;
        }

        // An event without data is not dispatched by EventSource.
        $this->response->write("\n");
    }

    /**
     * Write a data field, splitting multiline payloads correctly.
     */
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

        foreach (preg_split('/\r\n|\r|\n/', $payload) as $line) {
           $this->response->write("data: {$line}\n");
        }

        $this->response->write("\n");
    }

    /**
     * Write an SSE comment, commonly used for heartbeats.
     */
    private function writeComment(string $comment): void
    {
        foreach (preg_split('/\r\n|\r|\n/', $comment) as $line) {
            $this->response->write(": {$line}\n");
        }

        $this->response->write("\n");
    }

    /**
     * Write a protocol field.
     */
    private function writeField(string $name, string $value): void
    {
        if (preg_match('/[\r\n\0]/', $value)) {
            throw new InvalidArgumentException(
                "SSE {$name} cannot contain newline or null characters."
            );
        }

        $this->response->write("{$name}: {$value}\n");
    }
}
