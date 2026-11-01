<?php

namespace OktaaxExamples\Errors;

use Oktaax\Application;
use Oktaax\Exception\HttpException;
use Oktaax\Http\Request;
use Oktaax\Http\Response;
use Oktaax\Http\Response\File;

final class ApiProblem extends \RuntimeException {}

return function (array $config = []): Application {
    $app = new Application($config);
    $app->catch(
        ApiProblem::class,
        fn($error, $request, Response $response) => $response
            ->status(409)
            ->json([
                'error' => 'This operation conflicts with the current state.',
            ]),
    );
    $app->get(
        '/',
        fn() => new File(__DIR__ . '/views/errors.html', 200, [
            'content-type' => 'text/html; charset=utf-8',
        ]),
    );
    $app->get('/http/{status:\d+}', function (Request $request) {
        $status = (int) $request->param('status');
        if ($status < 400 || $status > 599) {
            throw new HttpException(400);
        }
        throw new HttpException($status);
    });
    $app->get('/exception', function () {
        throw new \RuntimeException(
            'The demonstration service is unavailable.',
        );
    });
    $app->get('/api/conflict', function () {
        throw new ApiProblem();
    });
    return $app;
};
