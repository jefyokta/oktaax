<?php

namespace Oktaax\Blade;

use Error;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;
use Oktaax\Http\Request;

class Blade
{

    /**
     * 
     * @var  Illuminate\View\Factory $viewFactory
     * 
     */
    private $viewFactory;

    /**
     * equals Oktaax\Oktaa::$config
     * @var array $config
     */
    private $config;


    /**
     * 
     * 
     */
    public function __construct(string $viewsDir, string $cacheDir, $config)
    {
        $container = new Container();
        $filesystem = new Filesystem();
        $dispatcher = new \Illuminate\Events\Dispatcher($container);

        $bladeCompiler = new BladeCompiler($filesystem, $cacheDir);

        //config from app config
        $this->config = $config;

        $engineResolver = new EngineResolver();
        $engineResolver->register('blade', function () use ($bladeCompiler, $filesystem) {
            return new CompilerEngine($bladeCompiler, $filesystem);
        });
        $engineResolver->register('php', function () use ($filesystem) {
            return new \Illuminate\View\Engines\PhpEngine($filesystem);
        });

        $viewFinder = new FileViewFinder($filesystem, [$viewsDir]);
        $this->viewFactory = new Factory($engineResolver, $viewFinder, $dispatcher);
        $this->registerDirectives($bladeCompiler);


        //calling functions
        require_once __DIR__ . "/function.php";


        //calling user functions
        if (!is_null($this->config['blade']['functionsDir'])) {
            if (file_exists($this->config['blade']['functionsDir'])) {
                require_once $this->config['blade']['functionsDir'];
            } else {
                throw new Error("File not found " . $this->config['blade']['functionsDir']);
            }
        }
    }
    private function registerDirectives(BladeCompiler $compiler)
    {

        $compiler->directive('method', function ($expression) {
            return "<?php echo \\Oktaax\\Blade\\BladeDirectives::methodField($expression); ?>";
        });

        $compiler->directive('okta', function () {
            return "<?php  sayHello(); ?>";
        });


        /**
         * @example
         * @requestHas("page")
         * {{ $request->all()->page }}
         * @endRequestHas
         * check if request has $key
         */
        $compiler->directive("requestHas", function ($key) {
            return "<?php if(\$request->has($key)): ?>";
        });


        $compiler->directive("endRequestHas", function () {
            return "<?php endif; ?>";
        });

        $compiler->directive("hasMessage", function () {
            return "<?php if(!is_null(\$request->cookie('X-Message'))): ?>\n<?php \$message = \$request->cookie('X-Message') ?>";
        });

        $compiler->directive("endHasMessage", function () {
            return "<?php endif; ?>";
        });

        $compiler->directive("hasErrorMessage", function () {
            return "<?php if(!is_null(\$request->cookie('X-ErrMessage'))): ?>\n<?php \$message = \$request->cookie('X-Message') ?>";
        });

        $compiler->directive("endHasErrorMessage", function () {
            return "<?php endif; ?>";
        });

        if ($this->config['app']['useCsrf']) {
            $compiler->directive("csrf", function () {
                return "<?php echo \\Oktaax\\Blade\\BladeDirectives::csrf(\$request); ?>";
            });
        }
    }

    public function render(string $view, array $data = []): string
    {


        return $this->viewFactory->make($view, $data)->render();
    }
}
