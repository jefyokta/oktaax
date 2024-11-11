<?php

namespace Example\Controller;

class HomeController
{
    public function index($req, $res)
    {
        $name = "jefyokta";
        $res->render("index",compact("name"));
    }
}
