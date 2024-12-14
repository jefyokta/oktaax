<?php

namespace Oktaax\Types;

class OktaaxConfig
{


    public function __construct(
        public string $viewDir,
        public string $render_engine,
        public string $logDir,
        public bool $useOktaMiddleware,
        public ?int $mode,
        public ?int $sock_type,
        public AppConfig $app,
        public BladeConfig $blade,
        public string $publicDir,

    ) {}
}
