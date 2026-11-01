<?php

namespace Oktaax\Http\Routing;

use Oktaax\Http\Request;
use Oktaax\Http\Response;

use Closure;
use InvalidArgumentException;
use Oktaax\Contracts\Middleware;

final class Pipeline
{
    /**
     * Normalized middleware and terminal handler callables, invoked with request, response, and next.
     *
     * @var list<\Closure(\Oktaax\Http\Request, \Oktaax\Http\Response, callable(): mixed): mixed>
     */
    private array $stacks = [];

    public function __construct(mixed ...$stacks)
    {
        foreach ($stacks as $stack) {
            if (is_string($stack) && class_exists($stack)) {
                $stack = new $stack();
            }
            if ($stack instanceof Middleware) {
                $stack = [$stack, 'handle'];
            }
            if (!is_callable($stack)) {
                throw new InvalidArgumentException(
                    'Invalid route handler or middleware.',
                );
            }
            $this->stacks[] = Closure::fromCallable($stack);
        }
    }

    public function __invoke(Request $request, Response $response): mixed
    {
        return $this->dispatch(0, $request, $response);
    }

    private function dispatch(
        int $index,
        Request $request,
        Response $response,
    ): mixed {
        if (!isset($this->stacks[$index])) {
            return null;
        }

        $handler = $this->stacks[$index];
        return $handler(
            $request,
            $response,
            fn() => $this->dispatch($index + 1, $request, $response),
        );
    }
}
