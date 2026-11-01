<?php

namespace Oktaax\Application;

use Oktaax\Application;
use Oktaax\Contracts\View;
use Oktaax\Event\EventEmitter;
use Oktaax\Event\MultiWorkerEventEmitter;
use Oktaax\Exception\ClientClosed;
use Oktaax\Exception\HttpException;
use Oktaax\Exception\ValidationException;
use Oktaax\Http\ErrorPageRenderer;
use Oktaax\Validation\Validator;
use Oktaax\View\PhpView;

final class Bootstrap
{
    public static function defaults(): array
    {
        return [
            'app' => [
                'host' => '127.0.0.1',
                'port' => 3000,
                'protocol' => 'http',
                'debug' => false,
                'name' => 'Oktaax',
                'chunk_size' => 8192,
            ],
            'server' => ['hook_flags' => SWOOLE_HOOK_ALL, 'worker_num' => 1],
        ];
    }

    /** Defaults are installed before user configuration, independently of worker events. */
    public static function register(Application $app): void
    {
        $app->container()->singleton(
            View::class,
            fn() => new PhpView('views/'),
        );
        $app->container()->alias('view', View::class);
        $app->container()->singleton(Validator::class, fn() => new Validator());
        $app->container()->singleton(
            ErrorPageRenderer::class,
            fn() => new ErrorPageRenderer(
                $app->config('app.error_views.http'),
                $app->config('app.error_views.exception'),
            ),
        );
        $app->container()->worker(
            EventEmitter::class,
            function () use ($app) {
                $server = $app->nativeServer();
                if (
                    $server instanceof \Swoole\Http\Server &&
                    ($server->setting['worker_num'] ?? 1) > 1
                ) {
                    return new MultiWorkerEventEmitter(
                        $app->worker,
                        fn($callback) => $app->within($callback),
                    );
                }
                return new EventEmitter(
                    fn($callback) => $app->within($callback),
                );
            },
            fn(EventEmitter $emitter) => $emitter->clear(),
        );
        $app->container()->alias('event', EventEmitter::class);
        $app->catch(ClientClosed::class, static function () {});
        $app->catch(HttpException::class, static function (
            $error,
            $request,
            $response,
        ) {
            if (!$response->isWritable()) {
                return;
            }
            if ($response->hasStarted()) {
                $response->end();
                return;
            }
            foreach ($error->getHeaders() as $name => $value) {
                $response->header($name, $value);
            }
            $response->renderHttpError($error->getStatusCode());
        });
        $app->catch(ValidationException::class, static function (
            $error,
            $request,
            $response,
        ) {
            if (!$response->isWritable()) {
                return;
            }
            if ($response->hasStarted()) {
                $response->end();
                return;
            }
            $response->status(422)->json(['errors' => $error->getErrors()]);
        });
        $app->kernel()
            ->exceptions()
            ->fallback(static function ($error, $request, $response) {
                if ($response === null) {
                    throw $error;
                }
                error_log((string) $error);
                if (!$response->isWritable()) {
                    return;
                }
                if ($response->hasStarted()) {
                    $response->end();
                    return;
                }
                $response->renderException($error);
            });
    }

    public static function boot(Application $app): void
    {
        if ($app->config('app.chunk_size') < 1) {
            throw new \InvalidArgumentException(
                'Response chunk size must be positive.',
            );
        }
        $app->router()->freeze();
        $app->websockets()->freeze();
        $app->config()->freeze();
        $app->container()->freeze();
    }
}
