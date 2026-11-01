<?php

namespace OktaaxExamples\WebSocket;

final readonly class Identity
{
    /** @param list<string> $allowedRooms */
    public function __construct(
        public string $id,
        public string $name,
        public string $role,
        public array $allowedRooms,
        public int $expiresAt,
    ) {}

    public function canJoin(string $room): bool
    {
        return in_array($room, $this->allowedRooms, true);
    }

    /** @return array{id: string, name: string, role: string, rooms: list<string>, expires_at: int} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'role' => $this->role,
            'rooms' => $this->allowedRooms,
            'expires_at' => $this->expiresAt,
        ];
    }
}
