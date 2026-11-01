<?php

use Oktaax\Application;
use Oktaax\Application\CallbackInvoker;
use Oktaax\Runtime\Swoole\CoroutineRuntime;

it(
    'rejects unsupported coroutine runtime settings before boot or socket creation',
    function () {
        $tasks = new Application(['server' => ['task_worker_num' => 1]]);
        expect(fn() => $tasks->run(new CoroutineRuntime()))->toThrow(
            LogicException::class,
            'does not support task workers',
        );
        expect($tasks->state())->toBe('configuring');
        $workers = new Application(['server' => ['worker_num' => 2]]);
        expect(fn() => $workers->run(new CoroutineRuntime()))->toThrow(
            LogicException::class,
            'one worker',
        );
    },
);

it(
    'resolves startup callbacks against the actual native runtime type',
    function () {
        $app = new Application();
        $address = $app->address();
        $called = null;
        CallbackInvoker::call(
            function (Application $actual, string $url) use (&$called) {
                $called = [$actual, $url];
            },
            [$app, $address],
            $address->getHttpUrl(),
        );
        expect($called)->toBe([$app, 'http://127.0.0.1:3000']);
        expect(
            fn() => CallbackInvoker::call(
                fn(DateTimeImmutable $missing) => null,
                [$app],
                '',
            ),
        )->toThrow(InvalidArgumentException::class);
    },
);
