<?php

namespace OktaaxExamples\MySql;

use Closure;
use Illuminate\Database\Connection;
use Oktaax\Exception\HttpException;
use Oktaax\Http\Request;
use SplObjectStorage;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

/** A bounded, lazy pool owned by one worker; each request holds an exclusive connection. */
final class ConnectionPool
{
    /** @var Channel<Connection|true> Empty slots reserve capacity before connecting. */
    private Channel $available;
    /** @var SplObjectStorage<Connection, 'idle'|'leased'|'returning'> */
    private SplObjectStorage $connections;
    private bool $closed = false;

    /** @param Closure(): Connection $connect */
    public function __construct(
        private Closure $connect,
        public readonly int $size = 4,
        private float $waitTimeout = 1.0,
    ) {
        if ($size < 1 || !is_finite($waitTimeout) || $waitTimeout <= 0) {
            throw new \InvalidArgumentException(
                'Pool size and wait timeout must be positive.',
            );
        }
        if (Coroutine::getCid() < 0) {
            throw new \LogicException(
                'Create the pool inside a worker coroutine.',
            );
        }
        $this->connections = new SplObjectStorage();
        $this->available = new Channel($size);
        for ($slot = 0; $slot < $size; $slot++) {
            $this->available->push(true);
        }
    }

    /** Acquire once for this handler and return the lease during request cleanup. */
    public function forRequest(Request $request): Connection
    {
        $connection = $this->acquire();
        try {
            $request->defer(fn() => $this->release($connection));
        } catch (\Throwable $error) {
            $this->release($connection);
            throw $error;
        }
        return $connection;
    }

    /**
     * Reserve a slot before any yielding connect or health check; wait at most waitTimeout seconds.
     *
     * @throws HttpException With HTTP 503 when the pool is closed or the wait expires.
     */
    public function acquire(): Connection
    {
        if ($this->closed) {
            throw $this->unavailable();
        }
        if (Coroutine::getCid() < 0) {
            throw new \LogicException(
                'Acquire connections inside a worker coroutine.',
            );
        }
        $slot = $this->available->pop($this->waitTimeout);
        if ($slot === false) {
            throw $this->unavailable();
        }
        $connection = $slot instanceof Connection ? $slot : null;
        try {
            $connection ??= ($this->connect)();
            $this->connections[$connection] = 'leased';
            // Detect idle disconnects before handing the connection to a transaction.
            $connection->selectOne('SELECT 1');
            if ($this->closed) {
                throw $this->unavailable();
            }
            return $connection;
        } catch (\Throwable $error) {
            if ($connection !== null) {
                $this->discard($connection);
            }
            $this->putBack(true);
            throw $error;
        }
    }

    /**
     * Roll back unfinished transactions before reuse; discard broken or explicitly dirty sessions.
     * Calling this again for an already returned connection has no effect.
     */
    public function release(Connection $connection, bool $discard = false): void
    {
        if (
            !$this->connections->offsetExists($connection) ||
            $this->connections[$connection] !== 'leased'
        ) {
            return;
        }
        $this->connections[$connection] = 'returning';
        if (!$discard && !$this->closed) {
            try {
                if ($connection->transactionLevel() > 0) {
                    $connection->rollBack(0);
                }
                // Also handle a transaction opened directly through PDO.
                $pdo = $connection->getPdo();
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $connection->flushQueryLog();
                $connection->disableQueryLog();
            } catch (\Throwable) {
                $discard = true;
            }
        }
        if ($discard || $this->closed) {
            $this->discard($connection);
            $this->putBack(true);
            return;
        }
        $this->connections[$connection] = 'idle';
        $this->putBack($connection);
    }

    /** Stop new leases, wake waiters, close idle connections, and close active ones on return. */
    public function close(): void
    {
        if ($this->closed) {
            return;
        }
        $this->closed = true;
        $this->available->close();
        foreach (clone $this->connections as $connection) {
            if ($this->connections[$connection] === 'idle') {
                $this->discard($connection);
            }
        }
    }

    private function unavailable(): HttpException
    {
        return new HttpException(503, 'Database pool is unavailable.', [
            'Retry-After' => '1',
        ]);
    }

    /** @param Connection|true $slot */
    private function putBack(Connection|bool $slot): void
    {
        if ($this->closed || !$this->available->push($slot, 0.001)) {
            if ($slot instanceof Connection) {
                $this->discard($slot);
            }
        }
    }

    private function discard(Connection $connection): void
    {
        try {
            $connection->disconnect();
        } finally {
            unset($this->connections[$connection]);
        }
    }
}
