<?php

namespace Oktaax\Http;

use Oktaax\Console;
use Oktaax\Contracts\Invokable;
use Oktaax\Application\ApplicationContext;
use Oktaax\Http\Request;
use Oktaax\Http\Response;

class Inertia extends Invokable
{
    /**
     * Render Inertia response
     * 
     * @param string $component
  
     * @param array $data 
     * 
     * @return void
     */
    public static function render(string $component, array $data = [])
    {
        $request = ApplicationContext::current()->getRequest();
        $response = ApplicationContext::current()->getResponse();

        $payload = [
            'component' => $component,
            'props' => $data,
            'url' => $request->server['request_uri'],
            'version' => null,
        ];

        if ($request->isInertia()) {
            $response->header('Content-Type', 'application/json');
            $response->header('X-Inertia', true);
            $response->end(json_encode($payload));
            return;
        }
        $response->render(
            view: ApplicationContext::current()->config('inertia.base_view'),
            data: ['page' => $payload],
        );
    }

    static function setBaseView(string $baseView)
    {
        ApplicationContext::current()
            ->config()
            ->set('inertia.base_view', $baseView);
    }
    public function __invoke()
    {
        self::render(...\func_get_args());
    }
}
