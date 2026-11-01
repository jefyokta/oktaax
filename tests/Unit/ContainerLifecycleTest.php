<?php

use Oktaax\Container\Container;

it(
    'resolves factories lazily and keeps application containers independent',
    function () {
        $built = 0;
        $a = new Container();
        $b = new Container();
        $a->singleton('service', function () use (&$built) {
            $built++;
            return new stdClass();
        });
        $b->singleton('service', fn() => new stdClass());
        expect($built)->toBe(0);
        expect($a->get('service'))
            ->toBe($a->get('service'))
            ->not->toBe($b->get('service'));
        expect($built)->toBe(1);
        expect(fn() => $a->get('missing'))->toThrow(
            OutOfBoundsException::class,
        );
    },
);

it(
    'builds worker services after start and disposes them once per worker generation',
    function () {
        $container = new Container();
        $built = 0;
        $disposed = [];
        $container->worker(
            'connection',
            function () use (&$built) {
                return (object) ['generation' => ++$built];
            },
            function ($resource) use (&$disposed) {
                $disposed[] = $resource->generation;
            },
        );
        expect(fn() => $container->get('connection'))->toThrow(
            LogicException::class,
        );
        expect($built)->toBe(0);
        $container->freeze();
        $container->startWorker();
        $first = $container->get('connection');
        expect($container->get('connection'))->toBe($first);
        $container->stopWorker();
        $container->stopWorker();
        expect($disposed)->toBe([1]);
        $container->startWorker();
        expect($container->get('connection'))->not->toBe($first);
        $container->stopWorker();
        expect($disposed)->toBe([1, 2]);
    },
);

it(
    'rejects circular dependencies and application singletons capturing worker services',
    function () {
        $container = new Container();
        $container->singleton('a', fn($c) => $c->get('b'));
        $container->singleton('b', fn($c) => $c->get('a'));
        expect(fn() => $container->get('a'))->toThrow(
            LogicException::class,
            'Circular',
        );
        $container->worker('worker', fn() => new stdClass());
        $container->singleton('long-lived', fn($c) => $c->get('worker'));
        $container->startWorker();
        expect(fn() => $container->get('long-lived'))->toThrow(
            LogicException::class,
            'cannot retain worker',
        );
        $container->stopWorker();
    },
);

it(
    'runs every disposer even when one fails and freezes definitions rather than service resolution',
    function () {
        $container = new Container();
        $disposed = [];
        $container->worker('first', fn() => new stdClass(), function () use (
            &$disposed,
        ) {
            $disposed[] = 'first';
        });
        $container->worker('last', fn() => new stdClass(), function () use (
            &$disposed,
        ) {
            $disposed[] = 'last';
            throw new RuntimeException('dispose failed');
        });
        $container->alias('alias', 'first');
        $container->freeze();
        expect(fn() => $container->register('late', new stdClass()))->toThrow(
            LogicException::class,
        );
        $container->startWorker();
        expect($container->get('alias'))->toBe($container->get('first'));
        $container->get('last');
        expect(fn() => $container->stopWorker())->toThrow(
            RuntimeException::class,
            'dispose failed',
        );
        expect($disposed)->toBe(['last', 'first']);
        $container->stopWorker();
    },
);
