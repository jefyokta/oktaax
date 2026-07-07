<?php

namespace Oktaax\Contracts;

use stdClass;

interface Jsonable
{
    public function toJson(): array|stdClass;
};
