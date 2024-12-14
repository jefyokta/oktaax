<?php

namespace Example\Controller;

class HomeController
{

    public function index($req, $res)
    {
        $name = "jefy okta";
        $res->render('index', compact("name"));
    }
};
