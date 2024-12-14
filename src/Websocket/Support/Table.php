<?php

namespace Oktaax\Websocket\Support;

use OpenSwoole\Table as SwooleTable;

class Table
{
    /**
     * The global Swoole Table instance.
     *
     * @var SwooleTable|null
     */
    private static ?SwooleTable $table = null;

    /**
     * Initialize the global Swoole Table.
     *
     * @param array $columns Format: ['column_name' => [type, size]]
     * @param int $size Maximum number of rows for the table.
     * 
     * @throws \RuntimeException If the table is already initialized.
     */
    public static function boot(SwooleTable $table): void
    {
        if (self::$table !== null) {
            throw new \RuntimeException('Table has already been initialized.');
        }
        $table->create();
        self::$table = $table;
    }

    /**
     * Get the global Swoole Table instance.
     *
     * @return SwooleTable
     * 
     * @throws \RuntimeException If the table has not been initialized.
     */
    public static function getTable(): SwooleTable
    {
        if (self::$table === null) {
            throw new \RuntimeException('Table has not been initialized.');
        }

        return self::$table;
    }

    /**
     * Find a row by its unique identifier (fd).
     *
     * @param mixed $fd The row identifier.
     * @return array|false The row data or false if not found.
     */
    public static function find($fd)
    {
        return self::getTable()->get($fd);
    }

    /**
     * Get a specific field or entire row by its identifier (fd).
     *
     * @param mixed $fd The row identifier.
     * @param string|null $field Specific field to retrieve, or null for the whole row.
     * @return mixed The field value or the entire row.
     */
    public static function get($fd)
    {
        return self::getTable()->get($fd);
    }

    /**
     * Retrieve all rows as a collection.
     *
     * @return array The collection of all rows.
     */
    public static function all(): array
    {
        $collection = [];

        foreach (self::getTable() as $row) {
            $collection[] = $row;
        }

        return $collection;
    }

    /**
     * Add a new row to the table.
     *
     * @param mixed $fd The row identifier.
     * @param array $data The row data to insert.
     * @return bool True if the row is successfully added, false otherwise.
     */
    public static function add($fd, array $data): bool
    {
        return self::getTable()->set($fd, $data);
    }

    /**
     * Remove a row by its identifier (fd).
     *
     * @param mixed $fd The row identifier.
     * @return bool True if the row is successfully removed, false otherwise.
     */
    public static function remove($fd): bool
    {
        return self::getTable()->del($fd);
    }
}
