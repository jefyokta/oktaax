<?php

namespace Oktaax\Runtime;

use Oktaax\Application;

interface ServerRuntime
{
    public function validate(Application $application): void;
    public function start(Application $application): void;
    public function stop(): void;
    public function supportsTasks(): bool;
    public function supportsWorkerMessages(): bool;
    public function supportsWebSockets(): bool;
}
