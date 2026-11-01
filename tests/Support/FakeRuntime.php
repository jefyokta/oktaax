<?php

namespace Tests\Support;

use Oktaax\Application;
use Oktaax\Runtime\ServerRuntime;

final class FakeRuntime implements ServerRuntime
{
    public bool $stopped = false;
    public function __construct(
        private \Closure $body,
        private ?object $server = null,
        private bool $tasks = false,
    ) {}
    public function validate(Application $application): void {}
    public function start(Application $application): void
    {
        if ($this->server !== null) {
            $application->attachServer($this->server);
        }
        $application->workers()->start(0);
        try {
            ($this->body)($application);
        } finally {
            $application->workers()->stop();
        }
    }
    public function stop(): void
    {
        $this->stopped = true;
    }
    public function supportsTasks(): bool
    {
        return $this->tasks;
    }
    public function supportsWorkerMessages(): bool
    {
        return false;
    }
    public function supportsWebSockets(): bool
    {
        return false;
    }
}
