<?php

namespace Oktaax\Http\Response;

class JsonEnvelope
{
    /**
     * Describe an explicit message/error/data envelope.
     *
     * @param array<array-key, mixed> $data
     * @param array<array-key, mixed> $error
     */
    public function __construct(
        private array $data = [],
        private string $message = '',
        private array $error = [],
    ) {}
    public function encode(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }
    /**
     * Return the envelope without serializing its payload.
     *
     * @return array{message: string, error: array<array-key, mixed>, data: array<array-key, mixed>}
     */
    protected function toArray(): array
    {
        return [
            'message' => $this->message,
            'error' => $this->error,
            'data' => $this->data,
        ];
    }
}
