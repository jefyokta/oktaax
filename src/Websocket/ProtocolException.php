<?php

namespace Oktaax\Websocket;

final class ProtocolException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $closeCode = 1008,
    ) {
        parent::__construct($message);
    }
}
