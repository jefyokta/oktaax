<?php

namespace Oktaax\Event;

use Closure;

class EventEmitter
{
    /**
     * Registered event listeners.
     *
     * @var array<string, array<int, Closure>>
     */
    private array $listeners = [];

    /**
     * Registered one-time event listeners.
     *
     * @var array<string, array<int, Closure>>
     */
    private array $onceListeners = [];

    /**
     * Register an event listener.
     */
    public function on(string $event, callable $listener): self
    {
        $this->listeners[$event][] = Closure::fromCallable($listener);

        return $this;
    }

    /**
     * Register a listener that runs only once.
     */
    public function once(string $event, callable $listener): self
    {
        $this->onceListeners[$event][] = Closure::fromCallable($listener);

        return $this;
    }

    /**
     * Remove event listeners.
     *
     * If no event is provided, all listeners are removed.
     * If no listener is provided, all listeners for the event are removed.
     */
    public function off(?string $event = null, ?callable $listener = null): self
    {
        if ($event === null) {
            return $this->clear();
        }

        if ($listener === null) {
            unset(
                $this->listeners[$event],
                $this->onceListeners[$event],
            );

            return $this;
        }

        $target = Closure::fromCallable($listener);

        foreach (['listeners', 'onceListeners'] as $property) {
            if (!isset($this->{$property}[$event])) {
                continue;
            }

            $this->{$property}[$event] = array_values(
                array_filter(
                    $this->{$property}[$event],
                    static fn (Closure $registered): bool => $registered !== $target,
                ),
            );

            if ($this->{$property}[$event] === []) {
                unset($this->{$property}[$event]);
            }
        }

        return $this;
    }

    /**
     * Dispatch an event to its registered listeners.
     */
    public function emit(string $event, mixed ...$payload): self
    {
        $listeners = $this->listeners[$event] ?? [];
        $onceListeners = $this->onceListeners[$event] ?? [];

        // Remove one-time listeners before execution to prevent re-entrant calls.
        unset($this->onceListeners[$event]);

        foreach ($listeners as $listener) {
            $listener(...$payload);
        }

        foreach ($onceListeners as $listener) {
            $listener(...$payload);
        }

        return $this;
    }

    /**
     * Check whether an event has registered listeners.
     */
    public function hasListeners(string $event): bool
    {
        return !empty($this->listeners[$event])
            || !empty($this->onceListeners[$event]);
    }

    /**
     * Get the number of listeners registered for an event.
     */
    public function listenerCount(string $event): int
    {
        return count($this->listeners[$event] ?? [])
            + count($this->onceListeners[$event] ?? []);
    }

    /**
     * Remove all registered listeners.
     */
    public function clear(): self
    {
        $this->listeners = [];
        $this->onceListeners = [];

        return $this;
    }
}
