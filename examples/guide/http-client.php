<?php

use Oktaax\Application;
use Oktaax\HttpClient\RequestOptions;
use function Oktaax\Utils\fetch;

return function (array $config = []): Application {
    $app = new Application($config);
    $app->get(
        '/upstream',
        fn() => ['message' => 'Hello from the local upstream'],
    );
    $app->get('/', function () use ($app) {
        $response = await(
            fetch(
                $app->address()->getHttpUrl() . '/upstream',
                new RequestOptions(timeout: 2),
            ),
        );
        try {
            if (!$response->ok) {
                throw new RuntimeException(
                    'Upstream returned HTTP ' . $response->status,
                );
            }
            $data = await($response->json());
            return [
                'upstream_status' => $response->status,
                'message' => $data->message,
            ];
        } finally {
            $response->close();
        }
    });
    return $app;
};
