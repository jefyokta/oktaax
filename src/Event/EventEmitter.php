<?php

namespace Oktaax\Event;

use Closure;
use Oktaax\Exception\EventDispatchException;

/**
 * Dispatch listeners synchronously within an optional owning execution scope; state is instance-owned.
 */
class EventEmitter
{
    /**
     * Optionally run each listener through an owning application-scope wrapper.
     *
     * @param (\Closure(\Closure(): mixed): mixed)|null $invoke
     */
    public function __construct(private ?Closure $invoke = null) {}
    /**
     * Listeners retained until explicitly removed or the owning scope is cleared.
     *
     * @var array<string, list<\Closure(\Oktaax\Event\Event<mixed>): mixed>>
     */
    protected array $listeners = [];

    /**
     * Register a listener; single=true skips registration when any listener already exists.
     *
     * @param callable(\Oktaax\Event\Event<mixed>): mixed $listener
     */
    public function on(
        string $event,
        callable $listener,
        bool $single = false,
    ): self {
        if ($single && \count($this->listeners[$event] ?? []) > 0) {
            return $this;
        }
        $this->listeners[$event][] = Closure::fromCallable($listener);

        return $this;
    }

    /**
     * Register a one-shot listener removed before invocation.
     *
     * @param callable(\Oktaax\Event\Event<mixed>): mixed $listener
     */
    public function once(string $event, callable $listener): self
    {
        $once = function (...$args) use ($event, $listener, &$once) {
            $this->off($event, $once);
            $listener(...$args);
        };
        return $this->on($event, $once);
    }

    /**
     * Remove all listeners, one event, or a specific listener using the same callable identity.
     *
     * @param (callable(\Oktaax\Event\Event<mixed>): mixed)|null $listener
     */
    public function off(?string $event = null, ?callable $listener = null): self
    {
        if ($event === null) {
            return $this->clear();
        }

        if ($listener === null) {
            unset($this->listeners[$event]);

            return $this;
        }

        $target = Closure::fromCallable($listener);
        if ($this->listeners[$event] === []) {
            unset($this->listeners[$event]);
        }

        if (isset($this->listeners[$event])) {
            $this->listeners[$event] = array_values(
                array_filter(
                    $this->listeners[$event],
                    static fn(Closure $registered): bool => $registered !==
                        $target,
                ),
            );
        }

        return $this;
    }

    /**
     * Invoke a snapshot of local listeners, attempt every listener, then report collected failures.
     *
     * @param array<array-key, mixed>|\Oktaax\Event\Event<mixed> $payload
     * @throws \Oktaax\Exception\EventDispatchException When one or more listeners fail.
     */
    public function emit(string $event, array|Event $payload = []): self
    {
        $listeners = $this->listeners[$event] ?? [];
        $ev =
            $payload instanceof Event ? $payload : new Event($event, $payload);
        $errors = [];

        foreach ($listeners as $listener) {
            try {
                if ($this->invoke !== null) {
                    ($this->invoke)(fn() => $listener($ev));
                } else {
                    $listener($ev);
                }
            } catch (\Throwable $th) {
                $errors[] = $th;
            }
        }

        if (!empty($errors)) {
            throw new EventDispatchException($event, $errors);
        }

        return $this;
    }

    /**
     * Check whether an event has registered listeners.
     */
    public function hasListeners(string $event): bool
    {
        return !empty($this->listeners[$event]);
    }

    /**
     * Get the number of listeners registered for an event.
     */
    public function listenerCount(string $event): int
    {
        return \count($this->listeners[$event] ?? []);
    }

    /**
     * Remove all registered listeners.
     */
    public function clear(): self
    {
        $this->listeners = [];

        return $this;
    }
}
