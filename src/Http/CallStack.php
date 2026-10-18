<?php

namespace Oktaax\Http;

use Oktaax\Console;

final class CallStack
{
    private static array $stacksMap = [];

    private array $stacks = [];

    public function __construct(mixed ...$stacks)
    {
        foreach ($stacks as $stack) {
            $key = $this->resolveKey($stack);

            if (isset($this->stacksMap[$key])) {
                $this->stacks[] = $key;
                continue;
            }

            self::$stacksMap[$key] = $stack;
            $this->stacks[] = $key;
        }

        $this->stacks = array_values(array_unique($this->stacks));
    }

    private function resolveKey(mixed $stack): string
    {
        if (is_object($stack)) {
            return 'object:' . spl_object_id($stack);
        }

        if (is_array($stack) && count($stack) === 2) {
            [$target, $method] = $stack;

            if (is_object($target)) {
                return 'method:' . spl_object_id($target) . ':' . $method;
            }

            return 'method:' . $target . ':' . $method;
        }

        if (is_string($stack)) {
            return 'string:' . $stack;
        }

        throw new \InvalidArgumentException(
            'Invalid route handler or middleware.'
        );
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

        $handler = $this->getHandler($index);

        $next = fn() => $this->dispatch(
            $index + 1,
            $request,
            $response,
        );

        return $handler($request, $response, $next);
    }

    private function getHandler(int $index)
    {
        return self::$stacksMap[$this->stacks[$index]];
    }
}
