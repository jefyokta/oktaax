<?php

namespace Oktaax\Attributes;

use Attribute;

#[Attribute(
    Attribute::TARGET_FUNCTION |
    Attribute::TARGET_METHOD
)]
class Async
{
}
