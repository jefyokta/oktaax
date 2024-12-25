<?php

namespace Oktaax\Support;

use SessionHandlerInterface;

class SessionTable implements SessionHandlerInterface {
    protected $table;

    public function __construct()
    {
        $this->table = new \Swoole\Table(1024);
        $this->table->column('data', \Swoole\Table::TYPE_STRING, 4096);
        $this->table->create();
    }

    public function open($savePath, $sessionName):bool
    {
        return true;
    }

    public function close():bool
    {
        return true;
    }

    public function read($sessionId):string|false
    {
        return $this->table->get($sessionId, 'data') ?? '';
    }

    public function write($sessionId, $data):bool
    {
        $this->table->set($sessionId, ['data' => $data]);
        return true;
    }

    public function destroy($sessionId):bool
    {
        $this->table->del($sessionId);
        return true;
    }

    public function gc($maxLifetime):int|false
    {
        return true;
    }
}
