<?php

namespace Oktaax\Http;

use Oktaax\Http\Request;
use Oktaax\Http\Response;



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
        $response = Response::getInstance();

        $payload = [
            'component' => $component,
            'props' => $data,
            'url' => $request->server['request_uri'],
            'version' => null
        ];

        if ($request->isInertia()) {
            $response->header('Content-Type', 'application/json');
            $response->header('X-Inertia', true);
            $response->end(json_encode($payload));
            return;
        }
        $response->render(self::$baseView, ['page' => $payload]);
    }

    /**
     * change your viewname
     */
    static function setBaseView(string $baseView)
    {
        self::$baseView = $baseView;
    }
}
