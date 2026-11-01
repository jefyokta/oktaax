<?php

namespace Oktaax\Http;

use Closure;
use Throwable;

class ExceptionHandler
{
    /**
     * Exception policies checked by concrete class and parents before compatible interfaces.
     *
     * @var array<class-string<\Throwable>, \Closure(\Throwable, \Oktaax\Http\Request|null, \Oktaax\Http\Response|null): mixed>
     */
    private array $handlers = [];
    /**
     * Final policy used when no registered exception type matches.
     *
     * @var (\Closure(\Throwable, \Oktaax\Http\Request|null, \Oktaax\Http\Response|null): mixed)|null
     */
    private ?Closure $fallback = null;

    /**
     * Register a handler for a throwable class or interface.
     *
     * @template TException of \Throwable
     * @param class-string<TException> $exception
     * @param \Closure(TException, \Oktaax\Http\Request|null, \Oktaax\Http\Response|null): mixed $handler
     */
    public function register(string $exception, Closure $handler): void
    {
        $this->handlers[$exception] = $handler;
    }
    /**
     * Set the final unmatched-exception policy; return values are ignored.
     *
     * @param \Closure(\Throwable, \Oktaax\Http\Request|null, \Oktaax\Http\Response|null): mixed $handler
     */
    public function fallback(Closure $handler): void
    {
        $this->fallback = $handler;
    }

    /**
     * Apply the most specific registered exception policy, fallback, or rethrow the original error.
     *
     * @throws \Throwable When no policy handles the error or the chosen handler fails.
     */
    public function dispatch(
        Throwable $error,
        ?Request $request = null,
        ?Response $response = null,
    ): void {
        for (
            $type = $error::class;
            $type !== false;
            $type = get_parent_class($type)
        ) {
            if (isset($this->handlers[$type])) {
                $this->handlers[$type]($error, $request, $response);
                return;
            }
        }
        foreach ($this->handlers as $type => $handler) {
            if ($error instanceof $type) {
                $handler($error, $request, $response);
                return;
            }
        }
        if ($this->fallback !== null) {
            ($this->fallback)($error, $request, $response);
            return;
        }
        throw $error;
    }
}
