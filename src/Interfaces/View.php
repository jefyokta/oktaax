<?php

namespace Oktaax\Interfaces;


interface View {
    public function render(string $view,array $data):?string ;
}
