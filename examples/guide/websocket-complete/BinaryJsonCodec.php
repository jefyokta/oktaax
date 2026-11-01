<?php

namespace OktaaxExamples\WebSocket;

use Oktaax\Websocket\Codec;
use Oktaax\Websocket\JsonCodec;
use Oktaax\Websocket\Message;
use Oktaax\Websocket\ProtocolException;

/** JSON envelopes over binary frames demonstrate changing the native opcode. */
final class BinaryJsonCodec implements Codec
{
    private readonly JsonCodec $json;

    public function __construct()
    {
        $this->json = new JsonCodec();
    }

    public function decode(string $payload, int $opcode): Message
    {
        if ($opcode !== SWOOLE_WEBSOCKET_OPCODE_BINARY) {
            throw new ProtocolException(
                'This endpoint accepts binary JSON frames only.',
                1003,
            );
        }
        return $this->json->decode($payload, SWOOLE_WEBSOCKET_OPCODE_TEXT);
    }

    public function encode(Message $message): string
    {
        return $this->json->encode($message);
    }

    public function opcode(): int
    {
        return SWOOLE_WEBSOCKET_OPCODE_BINARY;
    }
}
