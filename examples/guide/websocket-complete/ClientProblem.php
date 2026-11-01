<?php

namespace OktaaxExamples\WebSocket;

/** An expected failure whose message is safe to send to a client. */
final class ClientProblem extends \RuntimeException
{
    public function __construct(
        public readonly string $kind,
        string $message,
        public readonly ?int $closeCode = null,
    ) {
        parent::__construct($message);
    }
}
