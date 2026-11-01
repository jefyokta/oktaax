<?php

namespace OktaaxExamples\WebSocket;

use Oktaax\Websocket\Codec;
use Oktaax\Websocket\Message;
use Oktaax\Websocket\ProtocolException;

/** An isolated endpoint demonstrates adapting an existing application's wire envelope. */
final class AlternateJsonCodec implements Codec
{
    public function decode(string $payload, int $opcode): Message
    {
        if ($opcode !== SWOOLE_WEBSOCKET_OPCODE_TEXT) {
            throw new ProtocolException(
                'This endpoint accepts JSON text only.',
                1003,
            );
        }
        try {
            $value = json_decode($payload, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ProtocolException(
                'Malformed alternate JSON message.',
                1007,
            );
        }
        if (
            !is_array($value) ||
            !str_starts_with(ltrim($payload), '{') ||
            !is_string($value['type'] ?? null) ||
            array_diff(array_keys($value), [
                'type',
                'payload',
                'request_id',
            ]) !== [] ||
            (isset($value['request_id']) && !is_string($value['request_id']))
        ) {
            throw new ProtocolException(
                'Expected type, optional payload, and optional string request_id.',
            );
        }
        return new Message(
            $value['type'],
            $value['payload'] ?? null,
            $value['request_id'] ?? null,
        );
    }

    public function encode(Message $message): string
    {
        $value = ['type' => $message->event(), 'payload' => $message->data()];
        if ($message->id() !== null) {
            $value['request_id'] = $message->id();
        }
        return json_encode(
            $value,
            JSON_THROW_ON_ERROR |
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES,
        );
    }

    public function opcode(): int
    {
        return SWOOLE_WEBSOCKET_OPCODE_TEXT;
    }
}
