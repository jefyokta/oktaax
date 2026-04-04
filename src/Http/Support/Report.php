<?php

namespace Oktaax\Http\Support;

use Oktaax\Core\Application;

class Report
{
    public function __invoke()
    {
        $response = Application::context()->response();
        return [
            "status" => $response->getStatus(),
            "payload" => $response->getContent(),
            "headers" => $response->getHeaders()
        ];
    }
}
