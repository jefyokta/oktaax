<?php

namespace Example\Controller;

class HomeController
{
    public function index($req, $res, $next)
    {
        $name = "Jepi Oktaaa";
        // $next();
        $res->render("index", compact("name"));
    }
}
