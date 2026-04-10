<?php 

namespace Oktaax\Interfaces;

interface Injectable {
    public static function inject(string $key, $value);
}