<?php

namespace Oktaax\Core\Event;

use Oktaax\Core\Application;
use Oktaax\Core\URL;
use Oktaax\Core\Worker;
use Oktaax\Utils\Invoker;
use Oktaax\Exception\HttpException;
use Oktaax\Exception\ValidationException;
use Oktaax\Http\Request;
use Oktaax\Http\Response;
use Oktaax\Http\Support\StreamedResponse;
use Oktaax\Websocket\Client;
use Oktaax\Websocket\Server as OktaaxWebsocketServer;
use ReflectionParameter;
use Swoole\Http\Server;
use Swoole\WebSocket\Server as WebsocketServer;

/**
 * @extends Event<array{Server|WebsocketServer,int}>
 */
class WorkerStart extends Event
{
    private Invoker $invoker;

    public function __construct(private URL $url, private $callback = null)
    {
        $this->invoker = new Invoker();
        $this->setupGlobalHandlers();
    }

    public function name(): string
    {
        return "workerstart";
    }

    public function handle(...$args): void
    {
        [$server, $workerId] = $this->unpack(...$args);

        $app = Application::getInstance();
        $app->worker = new Worker($workerId, $server->taskworker);

        if ($this->callback === null) {
            return;
        }

        $this->invoker
            ->addContext($app)
            ->addContext($server)
            ->setResolver(function (ReflectionParameter $param, array $contexts) use ($server, $app) {
                $type = $param->getType();
                $name = $type instanceof \ReflectionNamedType ? $type->getName() : null;
                return match ($name) {
                    'string', null => $this->url->getHttpUrl(),
                    Server::class, WebSocketServer::class => $server,
                    Application::class => $app,
                    OktaaxWebsocketServer::class => new OktaaxWebsocketServer($server, new Client(-1)),
                    URL::class => $this->url,
                    default => null
                };
            })
            ->call($this->callback);
    }

    private function setupGlobalHandlers(): void
    {
        $app = Application::getInstance();

        $app->catch(
            HttpException::class,
            fn($e) => Response::getInstance()->renderHttpError($e->getStatusCode())
        );

        $app->catch(ValidationException::class, function (ValidationException $e) {
            $request = Request::getInstance();
            $res = Response::getInstance()->status(422);
            if ($request->wantsJson()) $res->end(json_encode(["error" => $e->getData()]));
        });

        $app->respond(StreamedResponse::class, function ($stream, $req, $res) {
            $res->status($stream->getStatus());
            foreach ($stream->getHeaders() as $k => $v) $res->header($k, $v);
            $stream->getCallback()(fn($d) => $res->write($d));
            $res->end();
        });
    }
}
