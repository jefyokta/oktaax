<?php

namespace Oktaax\Database;

use Closure;
use Illuminate\Database\Connection;
use Oktaax\Application;
use Oktaax\Application\ApplicationContext;
use Oktaax\Http\Request;

/** Optional Illuminate bridge; each application request owns its connection. */
final class DB
{
    /** @param Closure(Request): Connection $connectionForRequest */
    private function __construct(private Closure $connectionForRequest) {}

    /**
     * Register a lazy connection provider before boot. The provider must register its request cleanup.
     *
     * @param Closure(Request): Connection $connectionForRequest
     */
    public static function register(
        Application $app,
        Closure $connectionForRequest,
    ): void {
        $app->container()->singleton(
            self::class,
            new self($connectionForRequest),
        );
    }

    /**
     * Resolve the default connection once per active HTTP request, including nested transactions.
     *
     * @throws \LogicException When no live request is active.
     * @throws \OutOfBoundsException When the provider or requested connection is not registered.
     */
    public static function connection(?string $name = null): Connection
    {
        if ($name !== null && $name !== 'default') {
            throw new \OutOfBoundsException(
                "Database connection is not registered: {$name}",
            );
        }
        $app = ApplicationContext::current();
        $request = $app->getRequest();
        if ($request === null || $request->scope()->isClosed()) {
            throw new \LogicException(
                'Database queries require an active HTTP request.',
            );
        }
        $connection = $app->context()->get(self::class);
        if (!($connection instanceof Connection)) {
            $provider = self::provider($app);
            $connection = ($provider->connectionForRequest)($request);
            $app->context()->set(self::class, $connection);
        }
        return $connection;
    }

    /**
     * Run on the request's connection; commit on return and roll back on failure.
     *
     * @template TResult
     * @param Closure(Connection): TResult $callback
     * @return TResult
     */
    public static function transaction(
        Closure $callback,
        int $attempts = 1,
    ): mixed {
        return self::connection()->transaction($callback, $attempts);
    }

    private static function provider(Application $app): self
    {
        return $app->container(self::class);
    }
}
