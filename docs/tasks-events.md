# Tasks, events, IPC, and timers

[Guide index](README.md) · [Tasks recipe](../examples/guide/tasks.php) ·
[Events recipe](../examples/guide/events.php)

## Task workers

Tasks run through `ProcessRuntime` with at least one task worker:

```php
use Oktaax\Application;
use Oktaax\Task\Taskable;

final class UppercaseTask implements Taskable
{
    public string $text = '';
    public function handle(): string
    {
        return strtoupper($this->text);
    }
}

$app = new Application([
    'server' => [
        'worker_num' => 1,
        'task_worker_num' => 1,
        'task_enable_coroutine' => true,
    ],
]);
$app->post('/uppercase', function ($request) use ($app) {
    $payload = $request->validate(['text' => 'required|string']);
    return [
        'result' => $app->tasks()->await(UppercaseTask::class, $payload, 2),
    ];
});
```

Start with `run(new ProcessRuntime())`, or configure the same settings on an
`Oktaax` instance before `listen()`. `CoroutineRuntime` rejects task-worker
configuration before opening a socket. Task dispatch needs an active worker;
await additionally requires an active coroutine.

The dispatcher constructs the task class without constructor arguments and
assigns payload values to existing properties. Use public typed properties and
serializable payloads/results. Services and native connections should be obtained
inside the task worker, not serialized from an HTTP worker.

`dispatch($class, $payload)` sends work without waiting and returns void.
`await($class, $payload, $timeout)` waits in seconds; negative or null timeout
means no deadline. `Oktaax\Task\Task::dispatch/await` are scoped convenience
methods using the current application.

## Failures and completion hooks

`await()` throws `TaskTimeoutException` on timeout and `TaskFailedException` for
an unhandled remote failure. `TaskFailedException::$remoteError` contains the
remote error description; it is not a reconstructed arbitrary exception object.
Worker stop cancels outstanding waits. Correlation IDs and waiter channels are
removed on success, timeout, dispatch failure, remote failure, or cancellation.
Late completion cannot recreate a waiter.

Timeout does not cancel the remote task: it may still perform side effects. Task
workers are not a durable job queue and provide no built-in retry, persistence,
or exactly-once delivery. Build idempotency and durable work tracking in your
application when needed.

A task may implement `Completable::onComplete($result): void` to observe successful
completion in the sending worker. `Catchable::catch(Throwable $error)` handles a
failure in the task worker. If that catch hook returns normally, the failure is
marked handled and await returns the task's result (null on failure); if the hook
throws, await observes that replacement failure. The guide recipe demonstrates
unhandled errors and maps them to explicit JSON 502/504 responses.

## Application event emitters

The application registers `EventEmitter::class` (also aliased as `'event'`) as a
worker service. Resolve it after worker start. Register persistent listeners in
`onWorkerStart`; register connection listeners with connection cleanup.

```php
use Oktaax\Event\EventEmitter;

$app->onWorkerStart(function ($worker, $app) {
    $app->container(EventEmitter::class)->on('updated', function ($event) {
        error_log('Updated ID: ' . $event->data['id']);
    });
});
// In a request or worker callback:
// $app->container(EventEmitter::class)->emit('updated', ['id' => 42]);
```

`on`, `once`, `off`, `hasListeners`, `listenerCount`, and `clear` manage listeners.
Keep the same closure reference when unsubscribing. Listener exceptions are
collected into `EventDispatchException` after other listeners have been attempted.
Listeners are cleared at worker stop.

With more than one process HTTP worker, the default service becomes
`MultiWorkerEventEmitter`: `emit()` delivers locally and sends the event to other
HTTP workers. Receiver listeners run locally without rebroadcast. `emitLocal()`
is available on that implementation when only local dispatch is intended.
IPC delivery is asynchronous and is not a shared atomic counter or durable log.
The events recipe shows a separate in-memory counter in each worker.

## Raw worker messages

`$app->worker->send($data, $workerId = null, $includeTaskWorkers = false)` sends to
a specified worker or the other HTTP workers. It requires a runtime supporting
IPC. Use serializable values. Set `$worker->onMessage` during `onWorkerStart` to
receive a `WorkerMessagedEvent` for raw messages, whose `data` is the payload and
`sender['id']` is the sender. Framework `Event` messages go through the emitter.
`workers()` and `taskWorkers()` enumerate peers, excluding the current worker.

## Timers

```php
$app->onWorkerStart(function ($worker) {
    if (!$worker->isTaskWorker()) {
        $worker->tick(1000, fn() => error_log('worker heartbeat'));
    }
});
```

Worker tick intervals are milliseconds. The worker tracks them and clears them
on stop. `Oktaax\Utils\setTimeout($callback, $milliseconds)` and
`setInterval($callback, $milliseconds)` capture the active application; corresponding
`clearTimeout($id)` and `clearInterval($id)` clear and forget timers. Call helpers
inside an active application scope. Connection-owned timers still need explicit
connection cleanup. Avoid using untracked native timers when their work belongs
to a shorter framework scope.
