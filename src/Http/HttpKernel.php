<?php

namespace Oktaax\Http;

use JefyOkta\PhpPromise\Promise;
use Oktaax\Application;
use Oktaax\Application\RequestContext;
use Oktaax\Contracts\View;
use Oktaax\Exception\CleanupException;
use Oktaax\Validation\Validator;

final class HttpKernel
{
    private ExceptionHandler $exceptions;
    private ResponseEmitter $emitter;

    public function __construct(private Application $application)
    {
        $this->exceptions = new ExceptionHandler();
        $this->emitter = new ResponseEmitter();
    }

    public function exceptions(): ExceptionHandler
    {
        return $this->exceptions;
    }
    public function emitter(): ResponseEmitter
    {
        return $this->emitter;
    }

    public function handle(RequestContext $context): void
    {
        $app = $this->application;
        $request = $context->request;
        $response = $context->response;
        $failure = null;
        try {
            if (in_array($app->state(), ['stopping', 'stopped'], true)) {
                throw new \Oktaax\Exception\HttpException(
                    503,
                    'Application is stopping.',
                );
            }
            $response->useErrorPages(
                $app->container(ErrorPageRenderer::class),
                (bool) $app->config('app.debug', false),
            );
            $request->useValidator($app->container(Validator::class));
            $request->useHelpers($app->requestHelpers());
            $response->useHelpers($app->responseHelpers());
            $response->useView($app->container(View::class));
            $response->setChunkSize($app->config('app.chunk_size'));
            $request->connection()->bind($app);
            $app->workers()->track($request, $response);
            foreach ($app->responseClosedCallbacks() as $callback) {
                $request
                    ->connection()
                    ->onClose(
                        fn($event) => $app->within(
                            fn() => $callback($request, $response, $event),
                        ),
                    );
            }
            $result = $app->router()->dispatch($request, $response);
            if ($result instanceof Promise) {
                $result = await($result);
            }
            $this->emitter->dispatch($result, $request, $response);
        } catch (\Throwable $error) {
            try {
                $this->exceptions->dispatch($error, $request, $response);
            } catch (\Throwable $unhandled) {
                $failure = $unhandled;
            }
        } finally {
            $callbacks = array_map(
                fn($cb) => fn() => $cb($request, $response),
                $app->afterRequestCallbacks(),
            );
            $callbacks[] = fn() => $context->scope()->close();
            try {
                CleanupException::run($callbacks);
            } catch (\Throwable $cleanup) {
                if ($failure === null) {
                    $failure = $cleanup;
                } else {
                    error_log('Request cleanup also failed: ' . $cleanup);
                }
            } finally {
                $app->context()->destroy();
            }
        }
        if ($failure !== null) {
            throw $failure;
        }
    }
}
