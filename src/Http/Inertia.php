<?php

use Oktaax\Http\Request;
use Oktaax\Http\Response;

namespace Oktaax\Http;

class Inertia
{

    private static string $baseView = "app";
    /**
     * Render Inertia response
     * 
     * @param string $component
  
     * @param array $data 
     * 
     * @return void
     */
    public static function render($component, array $data = [])
    {
        $request = Request::getInstance();
        $response =  Response::getInstance();
        $props = $data;
        if (!$component) {
            throw new \InvalidArgumentException("Inertia render requires 'component' key");
        }

        if ($request->isInertia()) {
            $response->header('Content-Type', 'application/json');
            $response->header('X-Inertia', true);
            $payload = [
                'component' => $component,
                'props' => $props,
                'url' => $request->server['request_uri'],
                'version' => null
            ];
            $response->end(json_encode($payload));
        } else {
            $page = json_encode([
                'component' => $component,
                'props' => $props,
                'url' => $request->server['request_uri'],
                'version' => null
            ]);
            $response->render(self::$baseView, ['page' => $page]);
        }
    }

    /**
     * change your viewname
     */
    static function setBaseView(string $baseView)
    {
        self::$baseView = $baseView;
    }
}
