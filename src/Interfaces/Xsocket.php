<?php

namespace Oktaax\Interfaces;


interface Xsocket extends Server
{
    public function ws(string $event, callable|array $handler);
    public function gate(callable $callback);
    public function table(callable $callback, ?int $size = 1024);
}
