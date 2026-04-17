<?php

namespace Oktaax\Core\Event;

use Oktaax\Core\Application;
use Oktaax\Core\URL;
use Oktaax\Core\Worker;
use Oktaax\Utils\Invoker;
use Oktaax\Exception\HttpException;
use Oktaax\Exception\ValidationException;
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
        $app->worker = new Worker($workerId, $server->taskworker ?? false);

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
            fn($e) => Application::getResponse()->renderHttpError($e->getStatusCode())
        );

        $app->catch(ValidationException::class, function (ValidationException $e) {
            $request = Application::getRequest();
            $res = Application::getResponse()->status(422);
            $res->type('json')->end(json_encode(["errors" => $e->getErrors()]));
        });

        $app->respond(StreamedResponse::class, function ($stream, $req, $res) {
            $res->status($stream->getStatus());
            foreach ($stream->getHeaders() as $k => $v) $res->header($k, $v);
            $stream->getCallback()(fn($d) => $res->write($d));
            $res->end();
        });
    }
}
