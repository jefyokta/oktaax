<?php

namespace OktaaxExamples\Database;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Oktaax\Application;
use Oktaax\Database\DB;
use Oktaax\Exception\HttpException;
use Oktaax\Http\Request;
use Oktaax\Http\Response;

require_once __DIR__ . '/Note.php';

function registerNoteRoutes(Application $app, string $driver): void
{
    // Reuse the application's HTTP error policy, including any custom HTTP handler.
    $app->catch(ModelNotFoundException::class, static function (
        ModelNotFoundException $error,
        ?Request $request,
        ?Response $response,
    ) use ($app): void {
        $app->kernel()
            ->exceptions()
            ->dispatch(
                new HttpException(404, 'Note not found.'),
                $request,
                $response,
            );
    });
    $app->get(
        '/',
        fn() => [
            'example' => 'Eloquent + ' . $driver,
            'routes' => [
                'GET /notes',
                'POST /notes',
                'GET /notes/{id}',
                'PUT /notes/{id}',
                'DELETE /notes/{id}',
            ],
        ],
    );
    $app->get('/notes', function (): array {
        return [
            'data' => Note::query()
                ->orderByDesc('id')
                ->limit(50)
                ->get()
                ->toArray(),
        ];
    });
    $app->get('/notes/{id:\d+}', function (Request $request): array {
        $note = Note::findOrFail((int) $request->param('id'));
        return ['data' => $note->toArray()];
    });
    $app->post('/notes', function (Request $request, Response $response): void {
        $data = $request->validate([
            'title' => 'required|string',
            'body' => 'required|string',
        ]);
        $note = DB::transaction(static fn(): Note => Note::create($data));
        $response
            ->status(201)
            ->header('location', '/notes/' . $note->id)
            ->json(['data' => $note->toArray()]);
    });
    $app->put('/notes/{id:\d+}', function (Request $request): array {
        $data = $request->validate([
            'title' => 'required|string',
            'body' => 'required|string',
        ]);
        $note = DB::transaction(static function () use ($request, $data): Note {
            $note = Note::findOrFail((int) $request->param('id'));
            $note->fill($data)->save();
            return $note;
        });
        return ['data' => $note->toArray()];
    });
    $app->delete('/notes/{id:\d+}', function (
        Request $request,
        Response $response,
    ): void {
        $note = Note::findOrFail((int) $request->param('id'));
        $note->delete();
        $response->status(204)->end();
    });
}
