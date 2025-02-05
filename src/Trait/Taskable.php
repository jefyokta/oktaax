<?php


namespace Oktaax\Trait;


trait Taskable
{

    protected $tasks;

    public function __construct()
    {
        parent::__construct();
        $this->setServer('task_worker_num', 2);
        $this->setServer('task_enable_coroutine', true);
    }

    protected function eventRegistery()
    {

        if (method_exists(parent::class, 'eventRegistery')) {
            call_user_func([parent::class, 'eventRegistery']);
        };
       

        $this->server->on("Task", function ($server, $task) {
            $task->finish();
        });

        $this->server->on("Finish", function () {


        });
    }
    public function async($task){

        $this->tasks[$task] = $task;

        return $this;
    }


    public function then(callable $callback){
        
        $callback(21);

    }

    public function getHandledEvents()
    {
        $parent =  parent::getHandledEvents();
        return array_merge($parent, ['task', "finish"]);
    }
}
