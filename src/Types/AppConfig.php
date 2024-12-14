<?php

namespace Oktaax\Types;

class AppConfig
{


    public function __construct(
        public ?string $key,
        public ?bool $useCsrf,
        public int|float $csrfExp,
        public string $name
    ) {}
}
