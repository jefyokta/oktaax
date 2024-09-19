<?php

namespace Oktaax\Blade;

use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;

class Blade
{
    private $viewFactory;

    public function __construct(string $viewsDir, string $cacheDir)
    {
        $container = new Container();
        $filesystem = new Filesystem();
        $dispatcher = new \Illuminate\Events\Dispatcher($container);

        $bladeCompiler = new BladeCompiler($filesystem, $cacheDir);

        $engineResolver = new EngineResolver();
        $engineResolver->register('blade', function() use ($bladeCompiler, $filesystem) {
            return new CompilerEngine($bladeCompiler, $filesystem);
        });
        $engineResolver->register('php', function() use ($filesystem) {
            return new \Illuminate\View\Engines\PhpEngine($filesystem);
        });

        $viewFinder = new FileViewFinder($filesystem, [$viewsDir]);
        $this->viewFactory = new Factory($engineResolver, $viewFinder, $dispatcher);
    }

    public function render(string $view, array $data = []): string
    {
        return $this->viewFactory->make($view, $data)->render();
    }
}
