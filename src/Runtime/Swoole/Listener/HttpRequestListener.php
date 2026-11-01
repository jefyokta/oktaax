<?php

namespace Oktaax\Runtime\Swoole\Listener;

use Oktaax\Application;
use Oktaax\Http\Request;
use Oktaax\Http\Response;

final class HttpRequestListener
{
    public function __construct(private Application $application) {}

    public function handle(
        \Swoole\Http\Request $nativeRequest,
        \Swoole\Http\Response $nativeResponse,
    ): void {
        $request = new Request($nativeRequest);
        $response = new Response($nativeResponse, $request);
        $this->application->handleRequest($request, $response);
    }
}
