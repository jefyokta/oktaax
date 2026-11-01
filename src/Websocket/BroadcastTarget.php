<?php

namespace Oktaax\Websocket;

final readonly class BroadcastTarget
{
    public function __construct(
        private WebSocketKernel $kernel,
        private Endpoint $endpoint,
        private ?string $room = null,
        private ?int $except = null,
    ) {}

    public function except(Connection $connection): self
    {
        if ($connection->endpoint() !== $this->endpoint) {
            throw new \InvalidArgumentException(
                'Excluded connection belongs to another endpoint.',
            );
        }
        return new self(
            $this->kernel,
            $this->endpoint,
            $this->room,
            $connection->id(),
        );
    }
    public function send(string $event, mixed $data = null): void
    {
        $this->kernel->broadcast(
            $this->endpoint,
            new Message($event, $data),
            $this->room,
            $this->except,
        );
    }
}
