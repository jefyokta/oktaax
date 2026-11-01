<?php

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model as IlluminateModel;
use Illuminate\Database\SQLiteConnection;
use Oktaax\Application;
use Oktaax\Database\DB;
use Oktaax\Exception\HttpException;
use Oktaax\Http\Request;
use Oktaax\Http\Response;
use OktaaxExamples\Database\Note;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Tests\Support\Coroutines;
use Tests\Support\HttpFixture;
use function OktaaxExamples\Database\registerNoteRoutes;

require_once dirname(__DIR__, 2) . '/examples/guide/database/routes.php';

$connectionForRequest = static function (Request $request): Connection {
    $connection = new SQLiteConnection(new PDO('sqlite::memory:'));
    $connection->statement(
        'CREATE TABLE notes (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, body TEXT, created_at TEXT, updated_at TEXT)',
    );
    $request->defer(static fn() => $connection->disconnect());
    return $connection;
};

beforeEach(function () {
    if (!extension_loaded('pdo_sqlite')) {
        $this->markTestSkipped('Model tests require pdo_sqlite.');
    }
});

it(
    'uses normal Eloquent calls and one connection for the entire request',
    function () use ($connectionForRequest) {
        Coroutines::run(function () use ($connectionForRequest) {
            $app = new Application();
            $opened = [];
            DB::register($app, function (Request $request) use (
                $connectionForRequest,
                &$opened,
            ): Connection {
                $connection = $connectionForRequest($request);
                $opened[] = $connection;
                return $connection;
            });
            $app->get('/', function (Request $request): array {
                $connection = DB::connection();
                expect(DB::connection('default'))->toBe($connection);
                $note = DB::transaction(
                    static fn() => Note::create([
                        'title' => 'Created',
                        'body' => 'Body',
                        'id' => 99,
                    ]),
                );
                $note = Note::findOrFail($note->id);
                $note->fill(['title' => 'Updated'])->save();
                expect($note->getConnection())
                    ->toBe($connection)
                    ->and($note->id)
                    ->toBe(1);
                expect(
                    fn() => DB::transaction(static function (): void {
                        Note::create([
                            'title' => 'Rolled back',
                            'body' => 'Body',
                        ]);
                        throw new DomainException('Abort.');
                    }),
                )->toThrow(DomainException::class, 'Abort.');
                expect(Note::query()->count())
                    ->toBe(1)
                    ->and($connection->transactionLevel())
                    ->toBe(0);
                $request->defer(static function (): void {
                    expect(fn() => DB::connection())->toThrow(
                        LogicException::class,
                        'Database queries require an active HTTP request.',
                    );
                });
                return $note->toArray();
            });
            foreach ([1, 2] as $count) {
                $http = new HttpFixture();
                $app->handleRequest($http->request, $http->response);
                expect($http->nativeResponse->sentStatus)
                    ->toBe(200)
                    ->and(
                        json_decode($http->nativeResponse->sentBody, true)[
                            'title'
                        ],
                    )
                    ->toBe('Updated')
                    ->and(count($opened))
                    ->toBe($count)
                    ->and($opened[$count - 1]->getRawPdo())
                    ->toBeNull()
                    ->and($app->context()->get(DB::class))
                    ->toBeNull();
            }
            expect($opened[0])->not->toBe($opened[1]);
            expect(
                fn() => $app->within(fn() => Note::query()->count()),
            )->toThrow(LogicException::class);
        });
    },
);

it(
    'restores nested application scopes without changing Illuminate global state',
    function () use ($connectionForRequest) {
        Coroutines::run(function () use ($connectionForRequest) {
            $resolver = IlluminateModel::getConnectionResolver();
            $first = new Application();
            $second = new Application();
            DB::register($first, $connectionForRequest);
            DB::register($second, $connectionForRequest);
            $second->get(
                '/',
                fn() => Note::create([
                    'title' => 'Second application',
                    'body' => 'Second',
                ])->toArray(),
            );
            $first->get('/', function () use ($second): array {
                $note = Note::create([
                    'title' => 'First application',
                    'body' => 'First',
                ]);
                $connection = DB::connection();
                $nested = new HttpFixture();
                $second->handleRequest($nested->request, $nested->response);
                expect($nested->nativeResponse->sentStatus)
                    ->toBe(200)
                    ->and(
                        json_decode($nested->nativeResponse->sentBody, true)[
                            'title'
                        ],
                    )
                    ->toBe('Second application')
                    ->and(DB::connection())
                    ->toBe($connection);
                $note->title = 'Updated first';
                $note->save();
                return Note::findOrFail(1)->toArray();
            });
            $http = new HttpFixture();
            $first->handleRequest($http->request, $http->response);
            expect($http->nativeResponse->sentStatus)
                ->toBe(200)
                ->and(
                    json_decode($http->nativeResponse->sentBody, true)['title'],
                )
                ->toBe('Updated first')
                ->and(IlluminateModel::getConnectionResolver())
                ->toBe($resolver);
        });
    },
);

it(
    'isolates overlapping requests in the same application coroutine context',
    function () use ($connectionForRequest) {
        Coroutines::run(function () use ($connectionForRequest) {
            $app = new Application();
            DB::register($app, $connectionForRequest);
            $gate = new Channel(1);
            $done = new Channel(1);
            $app->get('/', function (Request $request) use ($gate): array {
                $title = $request->query('title');
                Note::create(['title' => $title, 'body' => 'Body']);
                if ($title === 'First request') {
                    $gate->pop(1);
                }
                return [
                    'title' => Note::findOrFail(1)->title,
                    'count' => Note::query()->count(),
                ];
            });
            $first = new HttpFixture(query: ['title' => 'First request']);
            $second = new HttpFixture(query: ['title' => 'Second request']);
            Coroutine::create(function () use ($app, $first, $done): void {
                try {
                    $app->handleRequest($first->request, $first->response);
                    $done->push(true);
                } catch (Throwable $error) {
                    $done->push($error);
                }
            });
            $app->handleRequest($second->request, $second->response);
            $gate->push(true);
            $result = $done->pop(1);
            if ($result instanceof Throwable) {
                throw $result;
            }
            expect($result)->toBeTrue();
            foreach (
                [[$first, 'First request'], [$second, 'Second request']]
                as [$http, $title]
            ) {
                expect($http->nativeResponse->sentStatus)
                    ->toBe(200)
                    ->and(json_decode($http->nativeResponse->sentBody, true))
                    ->toBe(['title' => $title, 'count' => 1]);
            }
        });
    },
);

it(
    'routes findOrFail through the application HTTP error policy',
    function () use ($connectionForRequest) {
        Coroutines::run(function () use ($connectionForRequest) {
            $default = new Application();
            DB::register($default, $connectionForRequest);
            registerNoteRoutes($default, 'test');
            $http = new HttpFixture(uri: '/notes/42');
            $default->handleRequest($http->request, $http->response);
            expect($http->nativeResponse->sentStatus)
                ->toBe(404)
                ->and($http->nativeResponse->sentHeaders['content-type'])
                ->toContain('text/html')
                ->and($http->nativeResponse->sentBody)
                ->toContain('A little off the map.');

            $custom = new Application();
            DB::register($custom, $connectionForRequest);
            registerNoteRoutes($custom, 'test');
            $custom->catch(HttpException::class, static function (
                HttpException $error,
                ?Request $request,
                ?Response $response,
            ): void {
                $response
                    ->status($error->getStatusCode())
                    ->json(['error' => 'Application policy']);
            });
            $http = new HttpFixture(uri: '/notes/42');
            $custom->handleRequest($http->request, $http->response);
            expect($http->nativeResponse->sentStatus)
                ->toBe(404)
                ->and(json_decode($http->nativeResponse->sentBody, true))
                ->toBe(['error' => 'Application policy']);
        });
    },
);
