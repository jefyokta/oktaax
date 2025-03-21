<?php

namespace Oktaax\Interfaces;



interface View {
    /**
     * Rendering Html View
     * @param string $view
     * @param array $data
     */
    public function render(string $view,array $data):?string ;
}
