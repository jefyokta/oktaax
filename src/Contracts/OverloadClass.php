<?php

namespace Oktaax\Contracts;
/**
 * @template T
 */
interface OverloadClass
{
    /**
     * @param T $instance
     * @return T
     */
    public function singleton($instance);
}
