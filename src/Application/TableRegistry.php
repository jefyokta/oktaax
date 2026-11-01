<?php

namespace Oktaax\Application;

use Swoole\Table;

final class TableRegistry
{
    /**
     * Native tables prepared before worker startup and owned by this application.
     *
     * @var array<string, \Swoole\Table>
     */
    private array $tables = [];
    private bool $preventRecreate = true;

    public function create(string $name, Table $table): void
    {
        if ($this->preventRecreate && isset($this->tables[$name])) {
            return;
        }
        $this->tables[$name] = $table;
    }
    public function get(string $name): ?Table
    {
        return $this->tables[$name] ?? null;
    }
    public function preventRecreate(bool $value = true): void
    {
        $this->preventRecreate = $value;
    }
}
