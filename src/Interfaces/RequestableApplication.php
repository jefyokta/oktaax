<?php

namespace Oktaax\Interfaces;

interface RequestableApplication extends Application
{

    public function getHost(): string;

    public function getRoutes(): array;
}
