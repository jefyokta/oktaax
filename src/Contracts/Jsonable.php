<?php

namespace Oktaax\Contracts;

use stdClass;

interface Jsonable
{
    /**
     * Return a structured value for JSON serialization; this method does not return pre-encoded JSON.
     *
     * @return array<array-key, mixed>|\stdClass
     */
    public function toJson(): array|stdClass;
}
