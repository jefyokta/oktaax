<?php

namespace Oktaax\Contracts;

use Stringable;

abstract class Streamable implements Stringable
{

    public function yield(): string|Stringable
    {
        return "";
    }
    final public function __toString()
    {
        return (string)$this->yield();
    }
}
