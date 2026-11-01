<?php

use Oktaax\Application;
use Oktaax\Exception\HttpException;
use Oktaax\Http\ErrorPageRenderer;
use Oktaax\Http\Middleware\Logger;
use Tests\Support\Coroutines;
use Tests\Support\HttpFixture;

beforeEach(function () {
    $this->errorLogFile = tempnam(sys_get_temp_dir(), 'oktaax-error-test-');
    $this->previousErrorLog = ini_set('error_log', $this->errorLogFile);
});
afterEach(function () {
    ini_set('error_log', $this->previousErrorLog);
    unlink($this->errorLogFile);
});

it(
    'renders a self-contained HTTP page with escaped request context and correct metadata',
    function () {
        Coroutines::run(function () {
            $app = new Application();
            $http = new HttpFixture(
                uri: '/missing/<script>alert(1)</script>?token=hidden-query',
            );
            $app->handleRequest($http->request, $http->response);
            expect($http->nativeResponse->sentStatus)->toBe(404);
            expect($http->nativeResponse->sentHeaders['content-type'])->toBe(
                'text/html; charset=utf-8',
            );
            expect($http->nativeResponse->sentBody)->toContain(
                'A little off the map.',
                '&lt;script&gt;alert(1)&lt;/script&gt;',
                'Back to home',
            );
            expect($http->nativeResponse->sentBody)->not->toContain(
                '<script>',
                'hidden-query',
                'fonts.googleapis.com',
            );
        });
    },
);

it(
    'uses generic production errors and escaped diagnostics only in debug mode',
    function (bool $debug) {
        Coroutines::run(function () use ($debug) {
            $app = new Application(['app' => ['debug' => $debug]]);
            $app->get('/fail', function () {
                throw new RuntimeException('<script>private-error</script>');
            });
            $http = new HttpFixture(uri: '/fail?secret=query-value');
            $app->handleRequest($http->request, $http->response);
            expect($http->nativeResponse->sentStatus)->toBe(500);
            expect($http->nativeResponse->sentBody)->not->toContain(
                '<script>private-error</script>',
                'query-value',
            );
            if ($debug) {
                expect($http->nativeResponse->sentBody)->toContain(
                    'DEBUG MODE',
                    '&lt;script&gt;private-error&lt;/script&gt;',
                    'Stack trace',
                    'ErrorPagesTest.php',
                    'source-line active',
                );
            } else {
                expect($http->nativeResponse->sentBody)->toContain(
                    'A pause, not the end.',
                );
                expect($http->nativeResponse->sentBody)->not->toContain(
                    'private-error',
                    'Stack trace',
                    'ErrorPagesTest.php',
                );
            }
        });
    },
)->with([false, true]);

it(
    'keeps HTTP exception policy when logger middleware is enabled',
    function () {
        Coroutines::run(function () {
            $app = new Application(['app' => ['debug' => true]]);
            $app->use(new Logger());
            $app->get(
                '/',
                fn() => throw new HttpException(
                    405,
                    headers: ['Allow' => 'POST'],
                ),
            );
            $http = new HttpFixture();
            $app->handleRequest($http->request, $http->response);
            expect($http->nativeResponse->sentStatus)->toBe(405);
            expect($http->nativeResponse->sentHeaders['allow'])->toBe('POST');
            expect($http->nativeResponse->sentBody)->not->toContain(
                'Stack trace',
            );
        });
    },
);

it(
    'uses application-owned template overrides and restores output buffers on template failure',
    function () {
        $template = tempnam(sys_get_temp_dir(), 'oktaax-error-view-');
        file_put_contents(
            $template,
            '<?php echo "custom:" . $escape($status) . ":" . $escape($requestPath);',
        );
        try {
            Coroutines::run(function () use ($template) {
                $app = new Application([
                    'app' => ['error_views' => ['http' => $template]],
                ]);
                $http = new HttpFixture(uri: '/absent');
                $app->handleRequest($http->request, $http->response);
                expect($http->nativeResponse->sentStatus)
                    ->toBe(404)
                    ->and($http->nativeResponse->sentBody)
                    ->toBe('custom:404:/absent');
            });
            file_put_contents(
                $template,
                '<?php echo "partial"; throw new RuntimeException("template failed");',
            );
            $renderer = new ErrorPageRenderer($template);
            $level = ob_get_level();
            expect(
                fn() => $renderer->http(404, (new HttpFixture())->request),
            )->toThrow(RuntimeException::class, 'template failed');
            expect(ob_get_level())->toBe($level);
        } finally {
            unlink($template);
        }
    },
);
