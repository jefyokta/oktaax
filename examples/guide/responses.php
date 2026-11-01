<?php

namespace OktaaxExamples\Responses;

use Oktaax\Application;
use Oktaax\Http\Request;
use Oktaax\Http\Response;
use Oktaax\Http\Response\File;
use Oktaax\View\PhpView;

final class CreatedItem
{
    public function __construct(public string $name) {}
}

return function (array $config = []): Application {
    $app = new Application($config);
    $app->setView(new PhpView(__DIR__ . '/views'));
    $app->respond(CreatedItem::class, function (
        CreatedItem $item,
        Request $request,
        Response $response,
    ) {
        $response->status(201)->json(['name' => $item->name]);
    });
    $app->get(
        '/',
        fn(Request $request, Response $response) => $response
            ->type('html')
            ->render('welcome', ['name' => $request->query('name', 'friend')]),
    );
    $app->get('/json', fn() => ['message' => 'This is raw JSON', 'ok' => true]);
    $app->get(
        '/envelope',
        fn($request, Response $response) => $response->jsonEnvelope(
            ['id' => 1],
            'Created',
        ),
    );
    $app->get('/custom', fn() => new CreatedItem('Notebook'));
    $app->get(
        '/redirect',
        fn($request, Response $response) => $response->redirect('/json'),
    );
    $app->get(
        '/cookie',
        fn($request, Response $response) => $response
            ->cookie('demo', 'hello', httponly: true)
            ->json(['cookie' => 'set']),
    );
    $app->get(
        '/download',
        fn() => new File(__DIR__ . '/files/hello.txt', 200, [
            'content-type' => 'text/plain; charset=utf-8',
            'content-disposition' => 'attachment; filename="hello.txt"',
        ]),
    );
    $app->get(
        '/stream',
        fn($request, Response $response) => $response->stream(
            function (callable $write) {
                foreach (["first\n", "second\n", "third\n"] as $chunk) {
                    if (!$write($chunk)) {
                        return;
                    }
                }
            },
            200,
            ['content-type' => 'text/plain; charset=utf-8'],
        ),
    );
    $app->get('/generator', function ($request, Response $response) {
        $response->type('text/plain; charset=utf-8');
        yield "first\n";
        yield "second\n";
    });
    return $app;
};
