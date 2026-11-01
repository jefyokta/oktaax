<?php

namespace Oktaax\Exception;

final class CleanupException extends \RuntimeException
{
    /** @param list<\Throwable> $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(
            count($errors) . ' lifecycle cleanup callbacks failed.',
            0,
            $errors[0] ?? null,
        );
    }

    /**
     * Attempt every callback; rethrow one failure or aggregate multiple failures in this exception.
     *
     * @param iterable<callable(): mixed> $callbacks
     * @throws \Throwable When any callback fails.
     */
    public static function run(iterable $callbacks): void
    {
        $errors = [];
        foreach ($callbacks as $callback) {
            try {
                $callback();
            } catch (\Throwable $error) {
                $errors[] = $error;
            }
        }
        if (count($errors) === 1) {
            throw $errors[0];
        }
        if ($errors !== []) {
            throw new self($errors);
        }
    }
}
