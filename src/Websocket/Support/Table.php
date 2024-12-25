<?php 
/**
 * Oktaax - Real-time Websocket and HTTP Server using Swoole
 *
 * @package Oktaax
 * @author Jefyokta
 * @license MIT License
 * 
 * @link https://github.com/jefyokta/oktaax
 *
 * @copyright Copyright (c) 2024, Jefyokta
 *
 * MIT License
 *
 * Copyright (c) 2024 Jefyokta
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 */
 


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
