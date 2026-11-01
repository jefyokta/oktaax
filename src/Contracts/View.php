<?php

namespace Oktaax\Contracts;

interface View
{
    /**
     * Render an application-controlled view name with template variables.
     *
     * @param array<string, mixed> $data
     */
    public function render(string $view, array $data): ?string;
}
