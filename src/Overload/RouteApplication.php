<?php

namespace Oktaax\Overload;

use Oktaax\Console;
use Oktaax\Oktaax;

class RouteApplication
{
    private $route = [];
    public function use(string $route, Oktaax $app)
    {
        $route = $this->parseUrl($route);
        echo $route;
        $this->route[$route] = $app;
    }

    /**
     * @return array<string,Oktaax>
     */
    function &getRoute()
    {
        return $this->route;
    }
    function parseUrl(string $path): string {
        $path = trim($path);                  
        $path = urldecode($path);              
        $path = preg_replace('/\/+/', '/', $path); 
        $path = rtrim($path, '/');             
        if ($path === '') $path = '/';         
        if ($path[0] !== '/') $path = '/' . $path; 
    
        return $path;
    }
    
}
