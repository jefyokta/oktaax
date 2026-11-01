<?php

namespace Oktaax\Http\Response;

use Oktaax\Application\ApplicationContext;

class Report
{
    /**
     * Inspect the active request response; payload reflects end() rather than a transcript of streamed writes.
     *
     * @return array{status: int, payload: mixed, headers: array<string, string>}
     * @throws \LogicException When there is no active application scope.
     */
    public function __invoke()
    {
        $response = ApplicationContext::current()->getResponse();
        return [
            'status' => $response->getStatus(),
            'payload' => $response->getContent(),
            'headers' => $response->getHeaders(),
        ];
    }
}
