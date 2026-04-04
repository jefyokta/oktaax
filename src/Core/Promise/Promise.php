<?php


namespace Oktaax\Core\Promise;

use Oktaax\Exception\AggregateError;
use Throwable;

use function Oktaax\Utils\spawn;
/**
 * still in development
 */
class Promise
{
    private PromiseState $state    = PromiseState::Pending;
    private mixed        $value    = null;
    private mixed        $reason   = null;
    private array        $handlers = [];

    public function __construct(?callable $executor = null)
    {
        if ($executor === null) return;

        spawn(function () use ($executor): void {
            try {
                $executor(
                    fn(mixed $value)  => $this->fulfill($value),
                    fn(mixed $reason) => $this->refuse($reason),
                );
            } catch (Throwable $e) {
                $this->refuse($e);
            }
        });
    }

    private function fulfill(mixed $value): void
    {
        if ($this->state !== PromiseState::Pending) return;

        if ($value instanceof static) {
            $value->then(
                fn($v) => $this->fulfill($v),
                fn($r) => $this->refuse($r),
            );
            return;
        }

        $this->state = PromiseState::Fulfilled;
        $this->value = $value;
        $this->flush();
    }

    private function refuse(mixed $reason): void
    {
        if ($this->state !== PromiseState::Pending) return;

        $this->state  = PromiseState::Rejected;
        $this->reason = $reason;
        $this->flush();
    }
    private function flush(): void
    {
        foreach ($this->handlers as $handler) {
            $this->dispatch($handler);
        }
        $this->handlers = [];
    }

    private function dispatch(array $handler): void
    {
        ['fulfill' => $onFulfilled, 'reject' => $onRejected, 'next' => $next] = $handler;

        spawn(function () use ($onFulfilled, $onRejected, $next): void {
            try {
                if ($this->state === PromiseState::Fulfilled) {
                    $next->fulfill($onFulfilled($this->value));
                } else {
                    $next->fulfill($onRejected($this->reason));
                }
            } catch (Throwable $e) {
                $next->refuse($e);
            }
        });
    }


    public function then(?callable $onFulfilled = null, ?callable $onRejected = null): static
    {
        $onFulfilled ??= fn($v) => $v;
        $onRejected  ??= fn($r) => throw (
            $r instanceof Throwable ? $r : new \RuntimeException((string) $r)
        );

        $next    = new static();
        $handler = ['fulfill' => $onFulfilled, 'reject' => $onRejected, 'next' => $next];

        $this->state === PromiseState::Pending
            ? $this->handlers[] = $handler
            : $this->dispatch($handler);

        return $next;
    }


    public function catch(callable $onRejected): static
    {
        return $this->then(null, $onRejected);
    }


    public function finally(callable $onFinally): static
    {
        return $this->then(
            function (mixed $value) use ($onFinally): mixed {
                $result = $onFinally();
                return ($result instanceof static)
                    ? $result->then(fn() => $value)
                    : $value;
            },
            function (mixed $reason) use ($onFinally): never {
                $result = $onFinally();
                if ($result instanceof static) $result->then(fn() => null);
                throw $reason instanceof Throwable
                    ? $reason
                    : new \RuntimeException((string) $reason);
            },
        );
    }


    public static function resolve(mixed $value = null): static
    {
        if ($value instanceof static) return $value;
        return new static(fn($resolve) => $resolve($value));
    }


    public static function reject(mixed $reason = null): static
    {
        return new static(fn($_, $reject) => $reject($reason));
    }


    public static function all(array $promises): static
    {
        return new static(function ($resolve, $reject) use ($promises): void {
            if (empty($promises)) {
                $resolve([]);
                return;
            }

            $total    = count($promises);
            $results  = array_fill(0, $total, null);
            $resolved = 0;
            $done     = false;

            foreach ($promises as $i => $item) {
                static::resolve($item)->then(
                    function (mixed $v) use ($i, $total, &$results, &$resolved, &$done, $resolve): void {
                        if ($done) return;
                        $results[$i] = $v;
                        if (++$resolved === $total) {
                            $done = true;
                            $resolve($results);
                        }
                    },
                    function (mixed $r) use (&$done, $reject): void {
                        if ($done) return;
                        $done = true;
                        $reject($r);
                    },
                );
            }
        });
    }

    public static function allSettled(array $promises): static
    {
        return new static(function ($resolve) use ($promises): void {
            if (empty($promises)) {
                $resolve([]);
                return;
            }

            $total   = count($promises);
            $results = array_fill(0, $total, null);
            $settled = 0;

            foreach ($promises as $i => $item) {
                static::resolve($item)->then(
                    function (mixed $v) use ($i, $total, &$results, &$settled, $resolve): void {
                        $results[$i] = ['status' => 'fulfilled', 'value' => $v];
                        if (++$settled === $total) $resolve($results);
                    },
                    function (mixed $r) use ($i, $total, &$results, &$settled, $resolve): void {
                        $results[$i] = ['status' => 'rejected', 'reason' => $r];
                        if (++$settled === $total) $resolve($results);
                    },
                );
            }
        });
    }

    public static function race(array $promises): static
    {
        return new static(function ($resolve, $reject) use ($promises): void {
            foreach ($promises as $item) {
                static::resolve($item)->then($resolve, $reject);
            }
        });
    }

    public static function any(array $promises): static
    {
        return new static(function ($resolve, $reject) use ($promises): void {
            if (empty($promises)) {
                $reject(new AggregateError([]));
                return;
            }

            $total    = count($promises);
            $errors   = array_fill(0, $total, null);
            $rejected = 0;
            $resolved = false;

            foreach ($promises as $i => $item) {
                static::resolve($item)->then(
                    function (mixed $v) use (&$resolved, $resolve): void {
                        if ($resolved) return;
                        $resolved = true;
                        $resolve($v);
                    },
                    function (mixed $r) use ($i, $total, &$errors, &$rejected, &$resolved, $reject): void {
                        if ($resolved) return;
                        $errors[$i] = $r;
                        if (++$rejected === $total) $reject(new AggregateError($errors));
                    },
                );
            }
        });
    }

    public function getState(): PromiseState
    {
        return $this->state;
    }
    public function isPending(): bool
    {
        return $this->state === PromiseState::Pending;
    }
    public function isFulfilled(): bool
    {
        return $this->state === PromiseState::Fulfilled;
    }
    public function isRejected(): bool
    {
        return $this->state === PromiseState::Rejected;
    }
}
