<?php

namespace Oktaax\Interfaces;


interface Server
{
    public function listen(int $port, string $host, callable $callback);
}
