<?php

namespace Oktaax\Core;

use Swoole\Table;

class TableRegistery
{
    /**
     * Summary of tables
     * @var array<string,Table>
     */
    private static $tables = [];

    private static bool $preventRecreate = true;

    public static function create(string $tableName, Table $table)
    {
        if(self::$preventRecreate && isset(self::$tables[$tableName])) return;

        self::$tables[$tableName] = $table;
    }

    private static function _create(string $tableName, Table $table)
    {
        if(self::$preventRecreate && isset(self::$tables[$tableName])) return;
        
        self::$tables[$tableName] = $table;
    }
    /**
     * Summary of get
     * @param string $tableName
     * @return ?Table
     */
    public static function get(string $tableName)
    {
        return   self::$tables[$tableName];
    }

    public static function preventRecreate($value = true){
        self::$preventRecreate = $value;
    }
};
