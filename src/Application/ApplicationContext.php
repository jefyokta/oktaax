<?php

namespace Oktaax\Application;

use LogicException;
use Oktaax\Application;
use Swoole\Coroutine;

/** Resolves the application bound to the current execution scope. */
final class ApplicationContext
{
    private const string KEY = 'oktaax.current_application';
    private static ?Application $synchronous = null;

    /**
     * Return the application explicitly bound to this execution scope.
     *
     * @throws LogicException When no application scope is active.
     */
    public static function current(): Application
    {
        $context = Coroutine::getContext();
        $application =
            $context === false || $context === null
                ? self::$synchronous
                : $context[self::KEY] ?? null;
        return $application ??
            throw new LogicException(
                'No application is active. Use Application::within() to enter an execution scope.',
            );
    }

    /**
     * Bind an application for one invocation and restore the previous coroutine or synchronous scope.
     * @template TResult
     * @param callable(): TResult $callback
     * @return TResult
     */
    public static function run(Application $app, callable $callback): mixed
    {
        $context = Coroutine::getContext();
        if ($context === false || $context === null) {
            $previous = self::$synchronous;
            self::$synchronous = $app;
            try {
                return $callback();
            } finally {
                self::$synchronous = $previous;
            }
        }
        $previous = $context[self::KEY] ?? null;
        $context[self::KEY] = $app;
        try {
            return $callback();
        } finally {
            if ($previous === null) {
                unset($context[self::KEY]);
            } else {
                $context[self::KEY] = $previous;
            }
        }
    }
}
