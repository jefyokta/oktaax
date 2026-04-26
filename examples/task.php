<?php

use Oktaax\Console;
use Oktaax\Core\Application;
use Oktaax\Core\Task\Catchable;
use Oktaax\Core\Task\Completable;
use Oktaax\Core\Task\Task;
use Oktaax\Core\Task\Taskable;
use Oktaax\Oktaax;

require __DIR__ . "/../vendor/autoload.php";

class MyTask implements
    //task class is must implement taskable, executed in task event
    Taskable,
    //optional if u want handle the task result (the return value from method handle()) this method is not executed in task event
    Completable,
    //optional if u want handle error (error from method handle()) this method is not executed in task event
    Catchable
{

    public string $name;
    public int $age;
    //execute in task event
    public function handle()
    {
        if ($this->name == 'okta') {
            return $this->name . $this->age;
        }

        throw new \Exception('Name must be okta');
    }
    public static function onComplete($result): void
    {
        Console::log($result);
        assert(str_starts_with($result,"okta"));
    }
    public static function catch(Throwable $th)
    {
        Console::error((string)$th);
    }
}
$app = new Oktaax;

$app->setServer("task_worker_num", 1);


$app->get("/task/{name}/{age}", function ($request, $response) {

    Task::dispatch(
        MyTask::class,
        //the array keys is your class property name, the application will inject properties value with this payload
        [
            "name" => $request->params['name'],
            "age" => (int)$request->params['age']
        ]
    );
    return "ok";
});

$app->listen(3000, function (Application $application, $url) {

    if (!$application
        ->worker
        ->isTaskWorker()) {
        Console::info("running on %s", $url);
    }
});
