<?php

namespace Oktaax\Websocket;

/**
 * Convert application messages to and from native WebSocket frame payloads.
 */
interface Codec
{
    /**
     * Decode one frame into an application message and reject unsupported or malformed framing.
     *
     * @throws \Oktaax\Websocket\ProtocolException When the payload cannot form a valid application message.
     */
    public function decode(string $payload, int $opcode): Message;
    /**
     * Encode the application envelope for the outbound opcode selected by opcode().
     */
    public function encode(Message $message): string;
    /**
     * Return the native WebSocket opcode used for encoded outbound payloads.
     */
    public function opcode(): int;
}
