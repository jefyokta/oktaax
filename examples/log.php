<?php
require __DIR__ . '/../vendor/autoload.php';

use Oktaax\Console;
use Oktaax\Task\Completable;
use Oktaax\Task\Task;
use Oktaax\Task\Taskable;
use Oktaax\Http\Request;
use Oktaax\Http\Response;
use Oktaax\Http\Response\Report;
use Oktaax\Oktaax;

$app = new Oktaax();

$app->config()->set('server.task_worker_num', 1);
$app->get('/', function (Request $request, Response $response) {
    $response->end('Hello from Oktaax!');
});

$app->get('/user/{id}', function (Request $request, Response $response) {
    $id = $request->params['id'] ?? 'unknown';
    $response->json([
        'message' => 'User details',
        'user_id' => $id,
        'query' => $request->get ?? [],
    ]);
});

$app->post('/submit', function (Request $request, Response $response) {
    $response->json([
        'received' => $request->all(),
        'timestamp' => time(),
    ]);
});

class ReportTask implements Taskable, Completable
{
    public array $log;
    public $requests;
    public function handle()
    {
        return file_put_contents(
            'log',
            json_encode(
                ['response' => $this->log, 'request' => $this->requests],
                JSON_PRETTY_PRINT,
            ) . "\n",
            FILE_APPEND,
        );
    }
    public static function onComplete($result): void
    {
        Console::log('appending log with size %s', $result);
    }
}

$app->afterRequest(function ($req, $res) {
    $report = new Report();
    Task::dispatch(ReportTask::class, [
        'log' => $report(),
        'requests' => $req->all(),
    ]);
});

$app->listen(3000);
