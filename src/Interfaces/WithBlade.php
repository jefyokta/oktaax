<?php


namespace Oktaax\Interfaces;



interface WithBlade
{
    public function useBlade(
        string   $viewDir = "views/",
        string $cachedir = "views/cache/",
        ?string $functionDir = null
    ): static;
}
