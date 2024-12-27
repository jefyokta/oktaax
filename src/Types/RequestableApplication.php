<?php 

namespace Oktaax\Types;
use Oktaax\Interfaces\RequestableApplication as Application;
use Oktaax\Oktaax;

class RequestableApplication extends Oktaax implements Application
{
    protected $routes;
    protected $host;
    protected $directory;
    protected $app;
    protected $usingLaravelWire = false;
    protected $interactWithSocket = false;

    public function withHost($host): static
    {
        $this->host = $host;
        return $this;
    }

    public function withDirectory($directory):static
    {

        $this->directory = $directory;
        return $this;
    }

    public function getHost(): string
    {
        return $this->host;
    }
}


?>