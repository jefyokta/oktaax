<?php
namespace Oktaax\Exception;
/**
 * Describe an unhandled remote failure without instantiating its arbitrary remote exception class.
 *
 * @phpstan-import-type RemoteError from \Oktaax\Task\TaskDispatcher
 */
final class TaskFailedException extends \RuntimeException
{
    /**
     * Keep the serialized remote failure description and expose its message/code.
     *
     * @param array{class?: class-string<\Throwable>|null, message?: string|null, code?: int, handled?: bool, file?: string, line?: int, trace?: string} $remoteError
     */
    public function __construct(public readonly array $remoteError)
    {
        parent::__construct(
            $remoteError['message'] ?? 'Remote task failed.',
            (int) ($remoteError['code'] ?? 0),
        );
    }
}
