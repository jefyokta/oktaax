<?php

namespace Oktaax\Container;

use Closure;
use InvalidArgumentException;
use LogicException;
use Oktaax\Exception\CleanupException;
use OutOfBoundsException;

/**
 * Resolve lazy services with separate application and worker lifetimes.
 *
 * @phpstan-type ServiceDefinition array{concrete: object, scope: 'application'|'worker', dispose: (\Closure(object): mixed)|null}
 */
final class Container
{
    /**
     * Factories or instances, lifetimes, and optional disposal callbacks.
     *
     * @var array<string, ServiceDefinition>
     */
    private array $definitions = [];
    /**
     * Resolved instances owned by this service lifetime.
     *
     * @var array<string, object>
     */
    private array $instances = [];
    /**
     * Resolved instances owned by this service lifetime.
     *
     * @var array<string, object>
     */
    private array $workerInstances = [];
    /**
     * Service aliases mapped directly to canonical service IDs.
     *
     * @var array<string, string>
     */
    private array $aliases = [];
    /**
     * Service IDs currently being resolved, used for lifetime and circular-dependency checks.
     *
     * @var array<string, true>
     */
    private array $resolving = [];
    private bool $frozen = false;
    private bool $workerStarted = false;

    /**
     * Register an application service instance or Closure(Container): object factory before boot.
     *
     * @param object $concrete
     * @throws \LogicException When definitions are frozen.
     */
    public function register(string $service, object $concrete): void
    {
        $this->singleton($service, $concrete);
    }

    /**
     * Register an application service instance or Closure(Container): object factory before boot.
     *
     * @param object $concrete
     * @param (\Closure(object): mixed)|null $dispose
     * @throws \LogicException When definitions are frozen.
     */
    public function singleton(
        string $service,
        object $concrete,
        ?Closure $dispose = null,
    ): void {
        $this->define($service, $concrete, 'application', $dispose);
    }

    /**
     * Register a lazy worker resource; its disposer runs at worker stop in reverse creation order.
     *
     * @param \Closure(self): object $factory
     * @param (\Closure(object): mixed)|null $dispose
     * @throws \LogicException When definitions are frozen.
     */
    public function worker(
        string $service,
        Closure $factory,
        ?Closure $dispose = null,
    ): void {
        $this->define($service, $factory, 'worker', $dispose);
    }

    /**
     * Store a service definition and invalidate any previously resolved instance.
     *
     * @param 'application'|'worker' $scope
     * @param (\Closure(object): mixed)|null $dispose
     */
    private function define(
        string $service,
        object $concrete,
        string $scope,
        ?Closure $dispose,
    ): void {
        if ($this->frozen) {
            throw new LogicException(
                'Service definitions are frozen after boot.',
            );
        }
        $service = $this->key($service);
        $this->definitions[$service] = [
            'concrete' => $concrete,
            'scope' => $scope,
            'dispose' => $dispose,
        ];
        unset($this->instances[$service], $this->workerInstances[$service]);
    }

    public function has(string $service): bool
    {
        $key = $this->key($service);
        return isset($this->definitions[$key]) ||
            isset($this->instances[$key]) ||
            isset($this->workerInstances[$key]);
    }

    /**
     * Resolve once within the declared lifetime; application factories cannot capture worker services.
     *
     * @throws \OutOfBoundsException When the service ID is not registered.
     * @throws \LogicException When worker scope is unavailable, dependencies are circular, or lifetimes conflict.
     * @throws \InvalidArgumentException When a factory returns an incompatible instance.
     */
    public function get(string $service): object
    {
        $key = $this->key($service);
        if (isset($this->workerInstances[$key])) {
            return $this->workerInstances[$key];
        }
        if (isset($this->instances[$key])) {
            return $this->instances[$key];
        }
        $definition =
            $this->definitions[$key] ??
            throw new OutOfBoundsException(
                "Service is not registered: {$service}",
            );
        if ($definition['scope'] === 'worker' && !$this->workerStarted) {
            throw new LogicException(
                "Worker service cannot be resolved before worker start: {$key}",
            );
        }
        if ($definition['scope'] === 'worker') {
            foreach (array_keys($this->resolving) as $parent) {
                if ($this->definitions[$parent]['scope'] === 'application') {
                    throw new LogicException(
                        'Application singletons cannot retain worker-scoped dependencies.',
                    );
                }
            }
        }
        if (isset($this->resolving[$key])) {
            throw new LogicException("Circular service dependency: {$key}");
        }
        $this->resolving[$key] = true;
        try {
            $concrete = $definition['concrete'];
            $instance =
                $concrete instanceof Closure ? $concrete($this) : $concrete;
            if (
                !is_object($instance) ||
                ((class_exists($key) || interface_exists($key)) &&
                    !($instance instanceof $key))
            ) {
                throw new InvalidArgumentException(
                    "Service factory returned an invalid instance for {$key}",
                );
            }
            if ($definition['scope'] === 'worker') {
                $this->workerInstances[$key] = $instance;
            } else {
                $this->instances[$key] = $instance;
            }
            return $instance;
        } finally {
            unset($this->resolving[$key]);
        }
    }

    public function alias(string $alias, string $serviceKey): void
    {
        if ($this->frozen) {
            throw new LogicException('Service aliases are frozen after boot.');
        }
        if (!$this->has($serviceKey)) {
            throw new InvalidArgumentException(
                "Cannot alias an unknown service: {$serviceKey}",
            );
        }
        $this->aliases[$alias] = $this->key($serviceKey);
    }

    /** Runtime-owned objects may change as workers restart; definitions remain frozen. */
    public function instance(string $service, object $instance): void
    {
        $service = $this->key($service);
        if (
            (class_exists($service) || interface_exists($service)) &&
            !($instance instanceof $service)
        ) {
            throw new InvalidArgumentException(
                "Invalid runtime instance for {$service}",
            );
        }
        $this->workerInstances[$service] = $instance;
    }

    public function startWorker(): void
    {
        if ($this->workerStarted) {
            throw new LogicException('Worker scope is already started.');
        }
        $this->workerStarted = true;
    }

    public function stopWorker(): void
    {
        $instances = $this->workerInstances;
        $this->workerInstances = [];
        $this->workerStarted = false;
        $this->dispose($instances);
    }

    public function close(): void
    {
        $instances = $this->instances;
        $this->instances = [];
        CleanupException::run([
            fn() => $this->stopWorker(),
            fn() => $this->dispose($instances),
        ]);
    }

    /**
     * Invoke available disposers in reverse creation order and attempt all of them.
     *
     * @param array<string, object> $instances
     * @throws \Throwable When one or more resource disposers fail.
     */
    private function dispose(array $instances): void
    {
        $callbacks = [];
        foreach (array_reverse($instances, true) as $key => $instance) {
            $dispose = $this->definitions[$key]['dispose'] ?? null;
            if ($dispose !== null) {
                $callbacks[] = static fn() => $dispose($instance);
            }
        }
        CleanupException::run($callbacks);
    }

    public function freeze(): void
    {
        $this->frozen = true;
    }

    private function key(string $service): string
    {
        $key = $this->aliases[$service] ?? $service;
        if (
            isset($this->definitions[$key]) ||
            isset($this->instances[$key]) ||
            isset($this->workerInstances[$key])
        ) {
            return $key;
        }
        return class_exists($key) || interface_exists($key)
            ? (new \ReflectionClass($key))->getName()
            : $key;
    }
}
