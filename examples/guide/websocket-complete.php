<?php

use Oktaax\Application;
use Oktaax\Application\WorkerContext;
use Oktaax\Event\EventEmitter;
use Oktaax\Exception\HttpException;
use Oktaax\Http\Request;
use Oktaax\Http\Response;
use Oktaax\Http\Response\File;
use OktaaxExamples\WebSocket\DemoAuth;
use OktaaxExamples\WebSocket\SocketDemo;

foreach (
    [
        'Identity',
        'ClientProblem',
        'DemoAuth',
        'AlternateJsonCodec',
        'BinaryJsonCodec',
        'SocketDemo',
    ]
    as $file
) {
    require_once __DIR__ . '/websocket-complete/' . $file . '.php';
}

return function (array $config = []): Application {
    $workers = filter_var(
        getenv('OKTAAX_WS_WORKERS') ?: '2',
        FILTER_VALIDATE_INT,
        [
            'options' => ['min_range' => 1, 'max_range' => 8],
        ],
    );
    $lifetime = filter_var(
        getenv('OKTAAX_WS_SESSION_SECONDS') ?: '1200',
        FILTER_VALIDATE_INT,
        [
            'options' => ['min_range' => 60, 'max_range' => 3600],
        ],
    );
    if ($workers === false || $lifetime === false) {
        throw new InvalidArgumentException(
            'Use 1–8 WS workers and a session lifetime of 60–3600 seconds.',
        );
    }
    $config['server']['worker_num'] = $workers;
    $config['server']['dispatch_mode'] ??= 2;
    $config['server']['package_max_length'] ??= 131072;
    $app = new Application($config);
    $scheme = $app->config('app.protocol') === 'https' ? 'https' : 'http';
    $port = $app->config('app.port');
    $suffix =
        ($scheme === 'https' && $port === 443) ||
        ($scheme === 'http' && $port === 80)
            ? ''
            : ':' . $port;
    $originConfig = getenv('OKTAAX_WS_ORIGINS');
    $origins =
        $originConfig === false
            ? [
                "{$scheme}://127.0.0.1{$suffix}",
                "{$scheme}://localhost{$suffix}",
            ]
            : array_values(
                array_filter(array_map('trim', explode(',', $originConfig))),
            );
    // Generate before fork so all workers verify the same cookie. A restart rotates this fallback key.
    $auth = new DemoAuth(
        getenv('OKTAAX_WS_SECRET') ?: bin2hex(random_bytes(32)),
        $origins,
        $lifetime,
    );
    $secureCookie = filter_var(
        getenv('OKTAAX_WS_SECURE_COOKIE') ?: '0',
        FILTER_VALIDATE_BOOL,
    );
    $logMessages = filter_var(
        getenv('OKTAAX_WS_LOG') ?: '0',
        FILTER_VALIDATE_BOOL,
    );

    $connections = implode(
        ' ',
        array_map(
            static fn(string $origin) => preg_replace('/^http/', 'ws', $origin),
            $origins,
        ),
    );
    $app->use(static function (
        Request $request,
        Response $response,
        callable $next,
    ) use ($connections) {
        $response
            ->header('cache-control', 'no-store')
            ->header('x-content-type-options', 'nosniff')
            ->header('referrer-policy', 'no-referrer')
            ->header(
                'content-security-policy',
                "default-src 'self'; connect-src 'self' {$connections}; frame-ancestors 'none'; base-uri 'none'; form-action 'self'",
            );
        return $next();
    });
    $assets = __DIR__ . '/websocket-complete';
    $app->get(
        '/',
        static fn() => new File($assets . '/client.html', 200, [
            'content-type' => 'text/html; charset=utf-8',
        ]),
    );
    $app->get(
        '/demo/client.js',
        static fn() => new File($assets . '/client.js', 200, [
            'content-type' => 'text/javascript; charset=utf-8',
        ]),
    );
    $app->get(
        '/demo/style.css',
        static fn() => new File($assets . '/style.css', 200, [
            'content-type' => 'text/css; charset=utf-8',
        ]),
    );
    $app->get(
        '/demo/session',
        static fn(Request $request) => [
            'user' => $auth->fromRequest($request)?->toArray(),
        ],
    );
    $app->post('/demo/session', static function (
        Request $request,
        Response $response,
    ) use ($auth, $secureCookie): void {
        $auth->requireOrigin($request);
        $data = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);
        $session = $auth->login($data['username'], $data['password']);
        $response
            ->cookie(
                DemoAuth::COOKIE,
                $session['token'],
                expires: $session['identity']->expiresAt,
                secure: $secureCookie || $request->protocol() === 'https',
                httponly: true,
                samesite: 'Strict',
            )
            ->json(['user' => $session['identity']->toArray()]);
    });
    $app->post('/demo/logout', static function (
        Request $request,
        Response $response,
    ) use ($auth, $secureCookie): void {
        $auth->requireOrigin($request);
        $response
            ->cookie(
                DemoAuth::COOKIE,
                '',
                expires: time() - 3600,
                secure: $secureCookie || $request->protocol() === 'https',
                httponly: true,
                samesite: 'Strict',
            )
            ->status(204)
            ->end();
    });
    $app->post('/demo/notice', static function (Request $request) use (
        $app,
        $auth,
    ): array {
        $auth->requireOrigin($request);
        $identity = $auth->fromRequest($request);
        if ($identity === null) {
            throw new HttpException(401);
        }
        if ($identity->role !== 'admin') {
            throw new HttpException(403);
        }
        $data = $request->validate(['text' => 'required|string']);
        if (trim($data['text']) === '' || strlen($data['text']) > 2000) {
            throw new \Oktaax\Exception\ValidationException([
                'text' => 'Use 1–2000 UTF-8 bytes.',
            ]);
        }
        $app->container(EventEmitter::class)->emit('demo.notice', [
            'text' => $data['text'],
            'by' => $identity->name,
        ]);
        return ['accepted' => true];
    });
    SocketDemo::register($app, $auth, $logMessages);
    $app->onWorkerStop(
        static fn(WorkerContext $worker) => error_log(
            '[ws] stopping ' . $worker->name,
        ),
    );
    return $app;
};
