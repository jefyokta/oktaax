<?php

namespace Oktaax\Websocket;

/** Internal worker-to-worker delivery; contains no native connections or callbacks. */
final readonly class Broadcast
{
    public function __construct(
        public string $path,
        public string $payload,
        public int $opcode,
        public ?string $room = null,
        public ?int $except = null,
        public ?int $exceptWorker = null,
    ) {}
}
