<?php

namespace Oktaax\Websocket;

use Closure;
use Oktaax\Application\RequestScope;
use Oktaax\Exception\ValidationException;
use Oktaax\Validation\Validator;

/**
 * A decoded application message with per-message cleanup independent of its connection lifetime.
 */
final class Message
{
    private RequestScope $scope;
    private ?Validator $validator = null;

    /**
     * Create an event/payload envelope with an optional string correlation ID.
     *
     * @throws \Oktaax\Websocket\ProtocolException When the event name or ID is empty, too long, or contains control characters.
     */
    public function __construct(
        private string $event,
        private mixed $data = null,
        private ?string $id = null,
    ) {
        if (
            $event === '' ||
            strlen($event) > 128 ||
            preg_match('/[\x00-\x1f\x7f]/', $event)
        ) {
            throw new ProtocolException(
                'Event must be a nonempty name of at most 128 bytes.',
            );
        }
        if (
            $id !== null &&
            ($id === '' ||
                strlen($id) > 128 ||
                preg_match('/[\x00-\x1f\x7f]/', $id))
        ) {
            throw new ProtocolException(
                'Message ID must be a nonempty string of at most 128 bytes.',
            );
        }
        $this->scope = new RequestScope();
    }

    public function event(): string
    {
        return $this->event;
    }
    public function data(): mixed
    {
        return $this->data;
    }
    public function id(): ?string
    {
        return $this->id;
    }
    /**
     * Register temporary message cleanup; the kernel attempts it on success or failure.
     *
     * @param \Closure(): mixed $callback
     */
    public function defer(Closure $callback): void
    {
        $this->scope->defer($callback);
    }
    public function useValidator(Validator $validator): void
    {
        $this->validator = $validator;
    }
    public function finish(): void
    {
        $this->scope->close();
    }

    /**
     * Validate the payload field map and return only input keys named in the rules.
     *
     * @param array<string, string|list<string>> $rules
     * @return array<array-key, mixed>
     * @throws \Oktaax\Exception\ValidationException When one or more validation rules fail.
     */
    public function validate(array $rules): array
    {
        if (!is_array($this->data)) {
            throw new ValidationException([
                'data' => [
                    'array' => 'Message data must be an object or array.',
                ],
            ]);
        }
        $errors = ($this->validator ??= new Validator())->validate(
            $this->data,
            $rules,
        );
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        return array_intersect_key($this->data, $rules);
    }
}
