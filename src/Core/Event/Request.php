<?php

namespace Oktaax\Core\Event;

use Oktaax\Console;
use Oktaax\Core\Application;
use Oktaax\Http\Request as HttpRequest;
use Oktaax\Http\Response;
use Oktaax\Types\OktaaxConfig;
use Swoole\Coroutine;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;

/**
 * @extends Event<array{SwooleRequest,SwooleResponse>}
 */
class Request extends Event
{
    public function __construct() {}
    public function name(): string
    {
        return "request";
    }

    public function handle(...$args): void
    {

        [$req, $res] = $this->unpack(...$args);

        $request = HttpRequest::create($req);
        
        //iteration in end function also has overhead
        $response = new Response($res);
        Application::setContext($request, $response)->handle();

    }
}
