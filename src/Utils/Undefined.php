<?php

namespace Oktaax\Utils;

use Oktaax\Utils\Interfaces\CanConvertToBoolean;
use Override;

final class Undefined implements CanConvertToBoolean
{
    private static ?self $instance = null;

    private function __construct() {}

    public static function get(): self
    {
        return self::$instance ??= new self();
    }

    public function __toString(): string
    {
        return 'undefined';
    }

    #[Override]
    public function getBooleanValue(): bool
    {
        return false;
    }
}