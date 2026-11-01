<?php

namespace Oktaax\Websocket;

final class ConnectionRegistry
{
    /**
     * Accepted or pending connections keyed by worker-local native descriptor.
     *
     * @var array<int, \Oktaax\Websocket\Connection>
     */
    private array $connections = [];

    public function add(Connection $connection): void
    {
        if (isset($this->connections[$connection->id()])) {
            throw new \LogicException('Connection is already registered.');
        }
        $this->connections[$connection->id()] = $connection;
    }
    public function get(int $fd): ?Connection
    {
        return $this->connections[$fd] ?? null;
    }
    public function remove(int $fd): ?Connection
    {
        $connection = $this->connections[$fd] ?? null;
        unset($this->connections[$fd]);
        return $connection;
    }
    /**
     * Return a snapshot so callbacks can safely remove connections during iteration.
     *
     * @return list<\Oktaax\Websocket\Connection>
     */
    public function all(): array
    {
        return array_values($this->connections);
    }
    public function count(): int
    {
        return count($this->connections);
    }
}
