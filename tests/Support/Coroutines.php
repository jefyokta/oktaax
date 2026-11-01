<?php

namespace Tests\Support;

final class Coroutines
{
    /** Propagate coroutine failures back to PHPUnit instead of the event loop. */
    public static function run(callable $callback): void
    {
        $error = null;
        \Swoole\Coroutine\run(function () use ($callback, &$error) {
            try {
                $callback();
            } catch (\Throwable $e) {
                $error = $e;
            }
        });
        if ($error !== null) {
            throw $error;
        }
    }
}
