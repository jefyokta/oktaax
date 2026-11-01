<?php

namespace Oktaax\Websocket;

/**
 * A runtime adapter for frame delivery, protocol close, and native connection liveness.
 */
interface Transport
{
    /**
     * Return whether the native transport accepted the frame payload for this descriptor.
     */
    public function send(int $fd, string $payload, int $opcode): bool;
    /**
     * Attempt a protocol close; connection cleanup is owned by the kernel regardless of the result.
     */
    public function close(int $fd, int $code, string $reason): bool;
    /**
     * Return whether this descriptor currently represents an established native WebSocket.
     */
    public function isOpen(int $fd): bool;
}
