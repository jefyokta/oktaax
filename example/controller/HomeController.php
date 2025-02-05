<?php

namespace Example\Controller;

use Oktaax\Http\Request;
use Oktaax\Http\Response;

class HomeController
{

    public function index(Request $req, Response $res)
    {
        $name = "jefy okta";
        $res->render('index', compact("name"));
    }
};
