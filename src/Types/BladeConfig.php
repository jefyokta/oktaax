<?php

namespace Oktaax\Types;

class BladeConfig
{

    public function __construct(public ?string $cacheDir, public ?string $functionDir) {}
};
