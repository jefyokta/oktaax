<?php

namespace Oktaax\Websocket;

/**
 * Encode event/data envelopes as JSON text; reject unsupported binary, malformed JSON, and invalid IDs.
 */
final class JsonCodec implements Codec
{
    /**
     * Decode JSON text while preserving false/null payloads and rejecting unknown envelope keys.
     *
     * @throws \Oktaax\Websocket\ProtocolException Carries the protocol close code for malformed or unsupported messages.
     */
    public function decode(string $payload, int $opcode): Message
    {
        if ($opcode !== SWOOLE_WEBSOCKET_OPCODE_TEXT) {
            throw new ProtocolException(
                'Only JSON text messages are supported.',
                1003,
            );
        }
        try {
            $value = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ProtocolException('Malformed JSON message.', 1007);
        }
        if (
            !is_array($value) ||
            !str_starts_with(ltrim($payload), '{') ||
            !isset($value['event']) ||
            !is_string($value['event']) ||
            array_diff(array_keys($value), ['event', 'data', 'id']) !== [] ||
            (isset($value['id']) && !is_string($value['id']))
        ) {
            throw new ProtocolException(
                'Expected an object containing event, optional data, and optional string id.',
            );
        }
        return new Message(
            $value['event'],
            $value['data'] ?? null,
            $value['id'] ?? null,
        );
    }

    /**
     * Encode event/data and include id only when supplied.
     *
     * @throws \JsonException When the envelope contains a value JSON cannot represent.
     */
    public function encode(Message $message): string
    {
        $value = ['event' => $message->event(), 'data' => $message->data()];
        if ($message->id() !== null) {
            $value['id'] = $message->id();
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
